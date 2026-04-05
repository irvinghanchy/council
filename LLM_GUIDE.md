# 線上議事簽到與表決系統 — LLM 維護指引

> **閱讀對象**：AI 助理（LLM）  
> **用途**：快速理解本專案的架構、邏輯、資料流，以進行維護、修改或客製化  
> **技術棧**：PHP 8.x・MySQL 8.x・DaisyUI 4 / Tailwind CSS・原生 JavaScript（無框架）  
> **部署環境**：XAMPP（本機）或 Apache + PHP-FPM（正式機）

---

## 一、專案定位與核心功能

本系統是一套**學生代表大會議事輔助平台**，服務三類使用者：

| 角色 | 入口 | 核心操作 |
|------|------|----------|
| **主辦人（Host/Admin）** | `admin/` | 設定會議、管理成員與議程、現場控制階段、審核動議 |
| **議員（Member）** | `member/index.php` | Google 登入即簽到、表決投票、申請發言、提臨時動議 |
| **大螢幕（Screen）** | `screen/index.php` | 黑底即時顯示：階段、人員出缺席、投票顏色、發言佇列 |

**最核心的設計概念**：系統在任何時刻只有一個「目前階段（phase）」。所有角色的顯示都以此為準，透過每 2 秒一次的 HTTP 輪詢（polling）同步。

---

## 二、檔案目錄結構

```
council/
├── config.php                  ← 【必改】DB 連線、Google OAuth、BASE_URL、SCREEN_PIN
├── index.php                   ← 登入入口，判斷角色後轉跳
├── install.php                 ← 首次安裝輔助（安裝後刪除）
│
├── auth/
│   ├── google_login.php        ← 啟動 Google OAuth 流程
│   ├── google_callback.php     ← OAuth 回調：驗證信箱→自動簽到→設定 session
│   ├── admin_login.php         ← 系統管理員帳號密碼登入頁
│   └── logout.php              ← 清除 session
│
├── admin/
│   ├── index.php               ← 後台總覽（統計數字、快速連結）
│   ├── setup.php               ← 建立/編輯會議、開始/結束會議
│   ├── members.php             ← 成員管理（單筆新增、批次匯入、刪除）
│   ├── agenda.php              ← 議程管理（新增報告/案由/選舉、候選人管理）
│   ├── control.php             ← 【最核心】現場控制台（切換階段、即時監控、動議審核）
│   └── hosts.php               ← 主辦人帳號管理（僅 admin 可見）
│
├── member/
│   └── index.php               ← 議員介面（投票按鈕、發言申請、臨時動議表單）
│
├── screen/
│   └── index.php               ← 大螢幕（黑底、人員格、投票顏色、即時輪詢）
│
├── api/                        ← 所有 AJAX 端點，JSON 回傳
│   ├── status.php              ← GET：取得全域狀態（輪詢主力）
│   ├── phase.php               ← POST：切換階段（主辦人）
│   ├── vote.php                ← POST：表決投票（議員）
│   ├── election_vote.php       ← POST：選舉投票（議員，SNTV 機制）
│   ├── close_vote.php          ← POST：截止表決/選舉，自動補棄權
│   ├── speech.php              ← GET/POST：發言佇列（申請/取消/管理）
│   ├── motion.php              ← GET/POST：臨時動議（送出/審核/主辦人直接新增）
│   ├── agenda_crud.php         ← POST：議程操作（標記當選、更新席次、排序）
│   └── export_txt.php          ← GET：下載會議紀錄 TXT
│
├── includes/
│   ├── functions.php           ← 【核心函式庫】所有共用邏輯
│   └── admin_layout.php        ← 後台共用頁首（含 navbar、require_host()）
│
└── db/
    ├── schema.sql              ← 完整資料庫建立語法
    └── connect.php             ← PDO 連線單例（db() 函式）
```

---

## 三、資料庫結構詳解

### 3.1 資料表關係圖（文字版）

```
meeting (1)
  ├──< members (N)          type: attendee | observer
  │     ├──< attendance (1)     簽到記錄，由 Google callback 自動建立
  │     ├──< votes (N)          每人每案由一筆
  │     ├──< election_votes (N) 每人每選舉可投 seats 張
  │     └──< speech_queue (N)   發言申請
  │
  ├── phase_control (1)     目前階段（唯一一筆，不斷 UPDATE）
  │
  ├──< agenda_items (N)     type: report | resolution | election | temp
  │     ├── resolutions (1)     案由詳情（目前僅作存在標記）
  │     └── elections (1)       選舉設定（seats 應選人數）
  │           └──< candidates (N)  候選人，member_id 可為 NULL（外部人員）
  │
  ├──< temp_motions (N)     議員提出的臨時動議，待主辦人審核
  └──< meeting_log (N)      事件流水帳（供匯出 TXT 用）
```

### 3.2 重要欄位說明

**`phase_control`**（全場只有一筆，UPDATE 不 INSERT）
```sql
phase_type     ENUM 'standby'|'agenda'|'resolution'|'election'|'temp_motion'|'ended'
agenda_item_id INT  -- 指向 agenda_items.id，NULL 表示 standby/ended
version        INT  -- 每次切換 +1，用於前端偵測變化
```

**`members`**
```sql
email      -- 學生信箱（Google OAuth 的唯一識別）
type       -- attendee（有表決權）| observer（列席，無表決權）
member_no  -- 議員編號（顯示用，可為空）
```

**`agenda_items`**
```sql
type    -- report | resolution | election | temp
status  -- pending（尚未開始）| open（進行中）| closed（已截止）
source  -- preset（會前設定）| host_added（主辦人臨時新增）| motion（受理的臨時動議）
```

---

## 四、核心函式庫（includes/functions.php）

### 最重要的函式：`build_status(int $meeting_id): array`

這是整個系統的「資料彙整器」，被 `api/status.php` 呼叫，回傳所有前端需要的資料：

```php
return [
    'meeting'         => [...],   // 會議基本資料
    'phase'           => [...],   // 目前階段（phase_type, agenda_item_id, version）
    'item'            => [...],   // 目前議程項目（null 若 standby）
    'election'        => [...],   // 選舉設定（null 若非選舉）
    'candidates'      => [...],   // 候選人列表（含得票數）
    'vote_stats'      => [...],   // 表決票數統計（null 若非表決）
    'members'         => [...],   // 所有成員 + 簽到狀態 + 投票狀態
    'speech_queue'    => [...],   // 發言佇列（waiting + speaking）
    'pending_motions' => int,     // 待審臨時動議數
    'agenda_list'     => [...],   // 議程清單（供導航用）
];
```

**`members` 陣列每筆包含**：
- `id`, `name`, `position`, `type`, `member_no`
- `signed_in` (0|1) — LEFT JOIN attendance
- `vote` ('yes'|'no'|'abstain'|null) — LEFT JOIN votes（WHERE agenda_item_id = 目前案由）

### 其他重要函式

```php
db(): PDO            // PDO 單例，全域使用 db()->prepare(...)->execute(...)
require_host()       // 未登入為主辦人則 redirect 首頁
require_member()     // 未登入則 redirect 首頁
is_host(): bool      // 目前 session 是否為主辦人角色
active_meeting()     // 取得 status IN ('preparing','active') 的最新會議，回傳 array|null
get_phase(int)       // 取得 phase_control 一筆，不存在則自動建立
set_phase(int, str, ?int) // 更新 phase_control（version++）
log_event(int, str, array) // 寫入 meeting_log
json_ok(mixed)       // 回傳 {"ok":true,"data":...} 並 exit
json_err(str, int)   // 回傳 {"ok":false,"error":...} 並 exit
h(string)            // htmlspecialchars 快捷
export_txt(int)      // 產生完整的會議紀錄文字檔
```

---

## 五、Session 結構

```php
// 主辦人（admin 或 host）
$_SESSION['role']       = 'admin' | 'host'
$_SESSION['host_id']    = hosts.id
$_SESSION['user_name']  = '秘書長'
$_SESSION['user_email'] = 'xxx@stu.nknush...'

// 議員（attendee）
$_SESSION['role']       = 'member'
$_SESSION['member_id']  = members.id
$_SESSION['meeting_id'] = meeting.id
$_SESSION['user_name']  = '王小明'
$_SESSION['user_email'] = 'xxx@stu.nknush...'

// 列席人
$_SESSION['role']       = 'observer'
// 其餘同議員
```

---

## 六、前端即時更新機制

所有頁面（member、screen、control）都使用**相同的輪詢模式**：

```javascript
async function poll() {
    const res = await fetch(`${BASE_URL}/api/status.php?meeting_id=${MEETING_ID}`);
    const d = await res.json();
    if (d.ok) updateUI(d.data);
    setTimeout(poll, 2000);  // POLL_MS 定義在 config.php，可改為 1000
}
```

- 輪詢間隔：`config.php` 的 `POLL_MS`（預設 2000ms）
- 沒有 WebSocket，沒有 SSE，純 HTTP 輪詢
- `phase_control.version` 每次切換 +1，前端可用來偵測「是否有狀態變化」（目前未強制用，但可加）

---

## 七、Google OAuth 流程

```
index.php → auth/google_login.php
    → 產生 state (CSRF 防護) 存入 session
    → redirect 至 Google 授權頁（帶 hd=ALLOWED_DOMAIN）

Google 回調 → auth/google_callback.php
    → 驗證 state（CSRF）
    → 換 access_token（POST to Google）
    → 解析 id_token 的 JWT payload（不驗簽，直接 base64 decode）
    → 取出 email，驗證網域 === ALLOWED_DOMAIN
    → 查 hosts 表 → 若是主辦人：設 session 為 host，redirect admin/
    → 查 members 表 → 若是議員：自動 INSERT IGNORE attendance，設 session，redirect member/
    → 都不是 → redirect 首頁帶錯誤碼
```

**注意**：id_token 的 JWT 驗簽省略了（僅做 base64 decode），生產環境建議加上 Google 公鑰驗證。

---

## 八、各 API 端點快速參考

| 路徑 | 方法 | 權限 | 說明 |
|------|------|------|------|
| `api/status.php` | GET | 登入者 / SCREEN_PIN | 回傳全域狀態（輪詢主力） |
| `api/phase.php` | POST | host | 切換階段 |
| `api/vote.php` | POST | member | 案由投票（yes/no/abstain） |
| `api/election_vote.php` | POST | member | 選舉投票（SNTV，多候選人） |
| `api/close_vote.php` | POST | host | 截止表決/選舉，自動補棄權 |
| `api/speech.php` | GET/POST | mixed | 發言佇列（request/cancel/update/list） |
| `api/motion.php` | GET/POST | mixed | 臨時動議（submit/review/host_add/list） |
| `api/agenda_crud.php` | POST | host | 議程 CRUD（set_elected/update_seats/reorder） |
| `api/export_txt.php` | GET | host | 下載會議紀錄 TXT |

**所有 API 統一回傳格式**：
```json
{ "ok": true,  "data": {...} }
{ "ok": false, "error": "錯誤說明" }
```

---

## 九、狀態機轉換規則

```
任何階段  ──主辦人操作──>  任何階段    （主辦人可自由跳轉）

特殊規則：
- resolution/election/agenda 階段，agenda_item_id 必須不為 null
- standby/temp_motion/ended 的 agenda_item_id = null
- 切換到 resolution/election 時，若 agenda_item.status='pending' → 自動改為 'open'
- 截止（close_vote.php）後 agenda_item.status → 'closed'
- status='closed' 的案由，投票 API 會拒絕（已截止）
```

**大螢幕人員格顏色邏輯**（screen/index.php 的 `updateScreen()`）：
```
phase = 'resolution'：
  member.vote = 'yes'     → 綠色（status-yes）
  member.vote = 'no'      → 紅色（status-no）
  member.vote = 'abstain' → 黃色（status-abstain）
  member.signed_in = 1    → 藍色閃爍（status-voting blink，尚未投票）
  member.signed_in = 0    → 灰色（status-absent）

其他 phase：
  member.signed_in = 1    → 白字深灰底（status-present）
  member.signed_in = 0    → 灰色（status-absent）

observer：固定深棕底、名字後加 ★
```

---

## 十、安裝步驟

1. 把 `council/` 資料夾放到 `htdocs/` 下
2. 開啟 `http://localhost/council/install.php`（僅限本機）
3. 匯入 `db/schema.sql` 至 MySQL
4. 填寫 `config.php`（DB、Google OAuth、BASE_URL、SCREEN_PIN）
5. 用 `install.php` 產生管理員密碼 hash，執行 UPDATE SQL
6. **刪除 `install.php`**
7. 前往 `http://localhost/council/`，以管理員帳號登入

---

## 十一、常見修改場景

### 場景 A：修改輪詢頻率
```php
// config.php
define('POLL_MS', 1000);  // 改為 1 秒
```

### 場景 B：允許多個學校信箱網域
```php
// config.php
define('ALLOWED_DOMAIN', 'stu.nknush.kh.edu.tw');
// auth/google_callback.php 第 47 行，把 === 改為 in_array：
$allowed = explode('|', ALLOWED_DOMAIN);
if (!in_array($domain, $allowed)) { /* redirect */ }
```

### 場景 C：加入表決過門檻邏輯
```php
// api/close_vote.php，在 log_event 前加：
$passed = ($stats['y'] > $stats['n']);  // 多數決
// 寫入 log 時帶入
log_event($meeting_id, 'resolution_closed', [..., 'passed' => $passed]);
```

### 場景 D：大螢幕顯示額外資訊
在 `screen/index.php` 的 `updateScreen()` 函式裡，`data` 物件包含 `build_status()` 的所有欄位，直接取用即可。

### 場景 E：新增議程類型
1. `db/schema.sql` 的 `agenda_items.type` ENUM 加入新值
2. `includes/functions.php` 的 `export_txt()` 加入對應輸出
3. `admin/agenda.php` 的 `$type_labels` 加入顯示名稱
4. `member/index.php` 加入對應的 panel HTML
5. `screen/index.php` 的 `phaseInfo` 物件加入對應 badge

### 場景 F：修改大螢幕配色
`screen/index.php` 的 `<style>` 區塊，修改 `.status-*` 的 CSS 即可。

---

## 十二、已知限制與可改善之處

| 項目 | 現況 | 建議改善 |
|------|------|----------|
| **即時性** | HTTP 輪詢每 2 秒 | 換成 Server-Sent Events 或 WebSocket |
| **JWT 驗簽** | 省略，直接 base64 decode | 加入 Google 公鑰驗簽 |
| **多會議** | 只支援一場 active 會議 | 加入 meeting_id 路由機制 |
| **表決修改** | 投票後無法更改 | 加入主辦人強制重設功能 |
| **列席人動議** | 列席人可提臨時動議 | 若不需要，在 motion.php submit 加 type 驗證 |
| **候選人排序** | 以 id 排序 | 加入 order_no 欄位 |
| **密碼強度** | 最低 6 字元 | 加入複雜度驗證 |

---

## 十三、資安注意事項

- **所有 API 都有 require_host() 或 require_member()** 驗證，不可移除
- **所有 DB 操作使用 Prepared Statement**，不拼接 SQL 字串
- **所有 HTML 輸出使用 `h()` 函式**（= htmlspecialchars），防 XSS
- `SCREEN_PIN` 是大螢幕唯一防護，若洩漏請立即更改
- `install.php` 安裝後必須刪除

---

## 十四、檔案修改時的注意事項

1. **不要**直接修改 `includes/functions.php` 中 `build_status()` 的回傳欄位名稱，因為 `member/index.php`、`screen/index.php`、`admin/control.php` 的 JS 都依賴這些名稱
2. 新增 API 時，記得在 `api/` 資料夾最頂部加入正確的 require_host() 或 require_member()
3. 所有需要 `$meeting` 變數的 admin 頁面，都透過 `includes/admin_layout.php` 自動取得
4. `admin_layout.php` 包含 require_host()，所有 admin 頁面只要 include 它就自動有權限驗證

---

*本文件由系統初始建置時自動產生，版本對應：2026 年 4 月*

---

## 十五、2026/4 版本更新記錄

### 新增功能

**計時器（Timer）**
- `api/timer.php`：主辦人 POST start/stop 控制
- `phase_control` 表新增 `timer_end_at`、`timer_label` 欄位
- `build_status()` 回傳 `timer: {end_ts, label}`，使用 Unix timestamp 避免時區問題
- 大螢幕顯示倒數，歸零後顯示正數紅字直到主辦人停止

**會議歷經時間**
- `meeting` 表新增 `actual_start_at`、`actual_end_at`
- `build_status()` 的 `meeting` 欄位包含這兩個時間戳
- 前端用 `data.meeting.actual_start_at`（字串）轉 Date 計算差值
- 結束會議時自動觸發 TXT 下載

**臨時動議改進**
- 受理時主辦人可選類型（討論/表決），`api/motion.php` 接收 `motion_type` 參數
- `build_status()` 新增 `motion_info`（提案人姓名/職稱），大螢幕顯示
- 送出後清空欄位，3 秒後提示消失

**選舉改進**
- 候選人支援逗號分隔大量匯入
- 快速加入從名單填入欄位（不自動送出）
- `api/status.php` 根據 session member_id 回傳 `my_election_voted`，解決跨選舉殘留
- `member/index.php` 改用 `myElecVotedItems[agenda_item_id]` 字典追蹤各選舉投票狀態

**議員可自行結束發言**
- `api/speech.php` update action：`status=done` 且帶 `member_id` 時，允許發言者本人操作，無需主辦人

**批次新增議程**
- `admin/agenda.php` 新增 `batch_agenda` action
- 格式：`type, 標題, 說明, 排序, 席次`

### 修正的 Bug

| Bug | 修正位置 |
|-----|----------|
| 按鈕跳動 | `assets/custom.css` 覆蓋 `.btn` transform |
| 臨時動議受理 SQL 參數數不符 | `api/motion.php` INSERT VALUES 補 `?` |
| 結束會議後 Admin 仍顯示進行中 | `control.php` setPhase('ended') 後 redirect |
| Timer 時區偏差 | 改用 Unix timestamp (`end_ts`) 傳給 JS |
| 列席人已簽到無鮮明顏色 | screen 拆成 `status-observer-present/absent` |
| Chrome 擴充功能殘留錯誤 | 攔截 `unhandledrejection` |

### 重要資料流變更

`api/status.php` 回傳結構新增欄位：
- `timer: { end_ts: int|null, label: string|null }`
- `motion_info: { content, proposer_name, proposer_position } | null`
- `my_election_voted: bool`（僅議員 session 時有效）
- `meeting.actual_start_at / actual_end_at`
