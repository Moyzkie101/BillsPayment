# 🚀 FULL AGENT PROMPT — Support Ticket System (Database + UI + Flow)

---

## 🎯 Objective

Build a **Support Ticket System UI and Backend** based on a structured, non-realtime, trail-based workflow.

The system must behave like:

* An email thread + audit trail combined
* Role-based workflow: **Branch → VPO → CAD**
* Section-based UI: Open, Active, Closed (role-dependent)
* Expandable **timeline cards**, not chat bubbles
* Every trail card shows a **DateTime stamp** for full transparency and accountability

---

# 🧠 CORE SYSTEM RULE

There is **NO realtime chat system**.

All updates, replies, and actions are stored and displayed as:

> ✅ **Ticket Trail Entries (timeline cards)**

Each trail card **always displays its DateTime** so it is visible who handled the ticket, when, and how long each step took.

---

# 📦 DATABASE STRUCTURE

---

## 1. `tickets`

```sql
tickets
- id (PK)
- ticket_number (unique, auto-generated: format TKT-YYYYMMDD-XXXX, e.g. TKT-20250615-0042)

- reference_number (VARCHAR) -- manually entered by Branch at creation
- source (ENUM: 'KPX', 'KP7') -- selected at creation
- partner_name (VARCHAR or FK → partners.id) -- selected via searchable dropdown at creation
- ticket_type (ENUM or FK → ticket_types.id) -- selected via dropdown at creation

- created_by (FK → users.id)
- created_by_role (ENUM: 'BRANCH', 'VPO', 'CAD') -- role of creator at time of creation

- assigned_to (nullable, FK → users.id) -- current active handler; set on Accept, cleared on Transfer
- vpo_owner (nullable, FK → users.id) -- set once when VPO accepts; never cleared (historical)
- cad_owner (nullable, FK → users.id) -- set once when CAD accepts; never cleared (historical; NULL if VPO closes without CAD)

- current_handler_role (ENUM: 'BRANCH', 'VPO', 'CAD') -- who currently holds the ticket

- status (ENUM:
    'open',       -- newly created, waiting for VPO
    'accepted',   -- VPO has accepted
    'resolving',  -- CAD has accepted
    'resolved',   -- VPO or CAD marked resolved, awaiting auto-close
    'closed'      -- closed (auto or immediately)
)

- reason (TEXT) -- free-form textbox, entered at creation

- allow_branch_reply (BOOLEAN, default TRUE) -- Branch can ALWAYS reply; this is always TRUE

- close_type (ENUM: 'auto', 'immediate', nullable) -- how the ticket was closed
- auto_close_at (DATETIME, nullable) -- set when resolved with auto-close option

- closed_at (DATETIME, nullable) -- set when ticket is actually closed
- created_at
- updated_at
```

> **Note on `allow_branch_reply`:** Branch can reply at any time regardless of ticket state. This field is kept for schema completeness but is always effectively TRUE. No footer hiding for Branch.

---

## 2. `partners` (lookup table)

```sql
partners
- id (PK)
- name (VARCHAR)
- created_at
```

> Used to populate the searchable Partner Name dropdown on ticket creation.

---

## 3. `ticket_types` (lookup table)

```sql
ticket_types
- id (PK)
- label (VARCHAR)
- created_at
```

> Used to populate the Ticket Type dropdown on ticket creation.

---

## 4. `ticket_trails`

```sql
ticket_trails
- id (PK)
- ticket_id (FK → tickets.id)

- type (ENUM:
    'message',      -- a reply/message was sent
    'accept',       -- VPO or CAD accepted the ticket
    'transfer',     -- ticket moved to a different role queue
    'resolve',      -- VPO or CAD marked ticket as resolved
    'close',        -- immediate close action
    'auto_close'    -- system closed the ticket automatically
)

- sender_id (FK → users.id, nullable) -- NULL for SYSTEM entries
- sender_role (ENUM: 'BRANCH', 'VPO', 'CAD', 'SYSTEM')

- target_role (nullable, ENUM: 'BRANCH', 'VPO', 'CAD') -- who this entry is directed to

- message (TEXT, nullable)

- meta (JSON, nullable)
  -- Examples:
  -- { "auto_close_duration": "3 days", "auto_close_at": "2025-06-18T10:00:00Z" }
  -- { "automation": true, "text": "Ticket has been transferred to CAD." }

- created_at  ← THIS IS THE DATETIME SHOWN ON EVERY TRAIL CARD
```

---

## 5. `ticket_attachments`

```sql
ticket_attachments
- id (PK)
- ticket_trail_id (FK → ticket_trails.id)
- ticket_id (FK → tickets.id) -- denormalized for easier querying

- file_name (VARCHAR)
- mime_type (VARCHAR)
- file_size (INT) -- bytes

- file_data (LONGBLOB) -- store binary directly in DB
  -- NOTE: If DB storage becomes a concern, replace with file_path (VARCHAR)
  -- and store files on disk/object storage (S3, etc.)

- meta (JSON, nullable)

- created_by (FK → users.id)
- created_at
```

**Supported file types:**

| Category | Types |
|---|---|
| Images | PNG, JPEG, JPG, GIF, WEBP |
| Documents | PDF, DOCX, TXT |
| Spreadsheets | XLSX, CSV, ODS |

---

# 🔄 TICKET LIFECYCLE & STATE MACHINE

```
[CREATED by Branch]
     ↓ status: open, current_handler_role: VPO
[VPO sees in Open queue]
     ↓ VPO Accepts → status: accepted, assigned_to: VPO user, vpo_owner: VPO user
[VPO Active]
     ├─ Reply (Submit) → message trail created, Branch can always see and reply
     ├─ Submit to CAD → type: transfer, current_handler_role: CAD, assigned_to: NULL
     │     [AUTOMATION TRAIL: "Ticket has been transferred to CAD."]
     │     [CAD sees in Open queue]
     │          ↓ CAD Accepts → status: resolving, assigned_to: CAD user, cad_owner: CAD user
     │     [CAD Active]
     │          ├─ Reply (Submit) → message trail created
     │          └─ Close Ticket →
     │               ├─ Auto Close → status: resolved, auto_close_at set
     │               └─ Close Immediately → status: closed, closed_at: NOW()
     └─ Close Ticket (VPO, without CAD) →
          ├─ Auto Close → status: resolved, auto_close_at set, cad_owner remains NULL
          └─ Close Immediately → status: closed, closed_at: NOW(), cad_owner remains NULL
[System Auto-Close Job]
     ↓ status: closed, closed_at: NOW() (for resolved tickets past auto_close_at)
```

---

# 🔁 STATE TRANSITION RULES

These rules must be enforced at the **backend (API level)**, not just the UI.

| Action | Performed By | Status Change | assigned_to | current_handler_role | cad_owner |
|---|---|---|---|---|---|
| Create Ticket | Branch | open | NULL | VPO | NULL |
| VPO Accept | VPO | accepted | VPO user | VPO | NULL |
| VPO Submit (reply) | VPO | accepted | unchanged | VPO | NULL |
| Branch Submit (reply) | Branch | unchanged | unchanged | unchanged | NULL |
| VPO Submit to CAD | VPO | accepted | **NULL** (cleared) | CAD | NULL |
| CAD Accept | CAD | resolving | CAD user | CAD | CAD user |
| CAD Submit (reply) | CAD | resolving | unchanged | CAD | unchanged |
| VPO Close (Auto) | VPO | resolved | unchanged | VPO | NULL |
| VPO Close (Immediate) | VPO | closed | unchanged | VPO | NULL |
| CAD Close (Auto) | CAD | resolved | unchanged | CAD | unchanged |
| CAD Close (Immediate) | CAD | closed | unchanged | CAD | unchanged |
| System Auto-Close | SYSTEM | closed | unchanged | unchanged | unchanged |

> **Key rule:** When VPO transfers to CAD, `assigned_to` is cleared to NULL so the ticket appears in CAD's Open queue (unassigned). `vpo_owner` is never cleared.
>
> **Key rule:** If VPO closes the ticket without ever sending to CAD, `cad_owner` remains NULL. The ticket will appear only in VPO's Closed section.

---

# 🤖 AUTOMATION TRAIL MESSAGES

Certain system actions must automatically generate a trail entry with `sender_role: 'SYSTEM'` and `meta: { "automation": true }`. These are **not user-generated** — they fire immediately after the triggering action.

| Trigger | Automation Message |
|---|---|
| VPO submits to CAD | "Ticket has been transferred to CAD." |
| CAD accepts ticket | "Ticket has been accepted by CAD and is now being resolved." |
| VPO closes immediately | "Ticket has been closed by VPO." |
| CAD closes immediately | "Ticket has been closed by CAD." |
| VPO sets auto-close | "Ticket has been marked as resolved. It will be automatically closed after [duration]." |
| CAD sets auto-close | "Ticket has been marked as resolved. It will be automatically closed after [duration]." |
| System auto-closes | "Ticket automatically closed after resolution period expired." |

> Automation trail cards should be visually distinct (e.g., ⚙️ SYSTEM icon, subtle gray/italic styling) from user-submitted messages.

---

# 🎨 UI STRUCTURE

---

## 🟢 Branch UI

**Sections:** Open | Closed *(no Active section)*

**Header:** Title + subtitle relevant to Branch role

**Create Ticket:** Prominent button — *Branch only. VPO and CAD do NOT have this.*

**Branch can always reply** — the Submit footer is always visible in the ticket modal regardless of ticket status.

---

### Open Section (Branch)

```sql
WHERE created_by = current_user AND status != 'closed'
```

### Closed Section (Branch)

```sql
WHERE created_by = current_user AND status = 'closed'
```

---

## 🔵 VPO UI

**Sections:** Open | Active | Closed

**No Create Ticket button.**

### Open Section (VPO)

```sql
WHERE current_handler_role = 'VPO' AND assigned_to IS NULL
```

> All VPO users see all unassigned VPO tickets.

### Active Section (VPO)

```sql
WHERE assigned_to = current_user AND current_handler_role = 'VPO'
```

### Closed Section (VPO)

```sql
WHERE status = 'closed' AND vpo_owner = current_user
```

---

## 🔴 CAD UI

**Sections:** Open | Active | Closed

**No Create Ticket button.**

### Open Section (CAD)

```sql
WHERE current_handler_role = 'CAD' AND assigned_to IS NULL
```

### Active Section (CAD)

```sql
WHERE assigned_to = current_user AND current_handler_role = 'CAD'
```

### Closed Section (CAD)

```sql
WHERE status = 'closed' AND cad_owner = current_user
```

---

# 🃏 TICKET CARD DESIGN (All Roles)

Each card displays:

* **Ticket Number** (e.g., TKT-20250615-0042)
* **Date Created** (below ticket number)
* **Ticket Type** (as card subtitle)
* **Partner Name**
* **Reason** (truncated preview)
* **Status Badge** (color-coded)

| Status | Badge Color |
|---|---|
| Open | Gray |
| Accepted | Blue |
| Resolving | Orange |
| Resolved | Green |
| Closed | Dark / Black |

---

# 📜 TICKET MODAL (All Roles)

Clicking any ticket card opens a modal.

---

## Modal Header

* Ticket Number
* Reference Number
* Source (KPX / KP7)
* Partner Name
* Ticket Type
* Reason
* Status Badge
* Current Handler Role indicator
* *(VPO Active only)* **Close Ticket** button (top-right)
* *(CAD Active only)* **Close Ticket** button (top-right)

---

## Modal Body — Trail Timeline

Render all `ticket_trails` for this ticket as **expandable/collapsible cards**, ordered oldest → newest.

### Trail Card Rules

Each trail card **always shows DateTime** (`created_at`) — this is non-negotiable for transparency and accountability.

| Element | Detail |
|---|---|
| Role Icon | 🟢 BRANCH / 🔵 VPO / 🔴 CAD / ⚙️ SYSTEM |
| Trail Type Label | Message / Accepted / Transferred / Resolved / Closed / Auto-Closed |
| **DateTime** | Full formatted date + time (e.g., Jun 15, 2025 — 10:42 AM) |
| Sender Name | Display name of the user who performed the action |
| Expand/Collapse toggle | Click anywhere on card |

**Default state:**
* Latest entry → **expanded**
* All older entries → **collapsed**

**Expanded view shows:**
* Full message text
* Attachments (file name, type icon, download link)
* Metadata (e.g., auto-close duration for resolve entries)

> The DateTime on each card makes it immediately visible how long each step took, who handled it, and where delays occurred.

---

## Modal Footer — Per Role & Section

---

### Branch (always visible — Branch can always reply)

```
[ + Attach Files ]  [ Message input text area ]  [ Submit ]
```

> Branch Submit always creates a `type: message` trail entry. There is no condition that hides this footer for Branch.

---

### VPO — Open section footer

```
[ Mediate ]  [ Accept ]
```

* **Accept** → runs Accept action (see state table), creates `type: accept` trail entry
* **Mediate** → closes modal, no action taken, no trail entry created

---

### VPO — Active section footer

```
[ + Attach Files ]  [ Message input text area ]  [ Submit ]  [ Submit to CAD ]
```

* **Submit** → creates `type: message` trail entry directed to Branch (general reply)
* **Submit to CAD** → creates `type: transfer` trail entry, clears `assigned_to`, sets `current_handler_role: CAD`, then fires automation trail: *"Ticket has been transferred to CAD."*

> The **Close Ticket** button is in the modal **header** (not the footer) for VPO Active.

---

### CAD — Open section footer

```
[ Mediate ]  [ Accept ]
```

Same behavior as VPO open footer. Accept fires automation trail: *"Ticket has been accepted by CAD and is now being resolved."*

---

### CAD — Active section footer

```
[ + Attach Files ]  [ Message input text area ]  [ Submit ]
```

* **Submit** → creates `type: message` trail entry directed to Branch (general reply)

> The **Close Ticket** button is in the modal **header** (not the footer) for CAD Active.

---

# 🔒 CLOSE TICKET FLOW (VPO & CAD)

Both VPO (Active) and CAD (Active) have a **Close Ticket** button in the modal header.

---

## Close Ticket Modal

```
Ticket: TKT-20250615-0042
Partner: [Partner Name]
Ticket Type: [Ticket Type]

──────────────────────────────
How would you like to close this ticket?
──────────────────────────────

○  Auto Close
   Close after: [ Number input ] [ Dropdown: Min | Hr | Days | Weeks | Mths | Yrs ]

○  Close Immediately

                              [ Cancel ]  [ Confirm Close ]
```

---

## On Confirm — Auto Close:

1. Calculate `auto_close_at = NOW() + duration`
2. Update ticket:
   * `status = 'resolved'`
   * `auto_close_at = calculated datetime`
   * `close_type = 'auto'`
3. Create trail entry (`type: resolve`):

```json
{
  "type": "resolve",
  "sender_id": user_id,
  "sender_role": "VPO" or "CAD",
  "meta": {
    "auto_close_duration": "3 days",
    "auto_close_at": "2025-06-18T10:00:00Z"
  }
}
```

4. Fire automation trail: *"Ticket has been marked as resolved. It will be automatically closed after [duration]."*

---

## On Confirm — Close Immediately:

1. Update ticket:
   * `status = 'closed'`
   * `closed_at = NOW()`
   * `close_type = 'immediate'`
2. Create trail entry (`type: close`):

```json
{
  "type": "close",
  "sender_id": user_id,
  "sender_role": "VPO" or "CAD",
  "message": "Ticket closed immediately."
}
```

3. Fire automation trail: *"Ticket has been closed by [VPO/CAD]."*

---

# ⏱ AUTO-CLOSE SYSTEM

A background job (cron / scheduled task) runs periodically:

```sql
SELECT * FROM tickets
WHERE status = 'resolved'
AND auto_close_at <= NOW()
```

For each matching ticket:

1. Update ticket:
   * `status = 'closed'`
   * `closed_at = NOW()`
2. Create trail entry:

```json
{
  "type": "auto_close",
  "sender_id": null,
  "sender_role": "SYSTEM",
  "message": "Ticket automatically closed after resolution period expired."
}
```

> Recommended job frequency: every 1–5 minutes.

---

# 🎫 TICKET CREATION FORM (Branch Only)

The create ticket form must capture the following fields in this exact layout:

```
┌─────────────────────────────────────────────────────┐
│  Ticket #: [Auto-generated — display only]          │
├─────────────────────────────────────────────────────┤
│  Reference Number:  [Text input]                    │
│  Source:            [ KPX | KP7 ] (radio or select) │
│  Partner Name:      [Searchable dropdown]           │
│  Ticket Type:       [Dropdown]                      │
├─────────────────────────────────────────────────────┤
│  Reason:            [Textarea / Textbox]            │
├─────────────────────────────────────────────────────┤
│  [ + Attach Files ]                                 │
│                                                     │
│                          [ Cancel ]  [ Submit ]     │
└─────────────────────────────────────────────────────┘
```

### Field Rules

| Field | Type | Required | Notes |
|---|---|---|---|
| Ticket # | Display only | — | Auto-generated (TKT-YYYYMMDD-XXXX), shown but not editable |
| Reference Number | Text input | Yes | Manual entry by Branch |
| Source | Radio / Select | Yes | Options: KPX, KP7 |
| Partner Name | Searchable dropdown | Yes | Populated from `partners` table |
| Ticket Type | Dropdown | Yes | Populated from `ticket_types` table |
| Reason | Textarea | Yes | Free-form text |
| Attachments | File upload | No | Multiple files allowed |

On Submit:
* Ticket is created with `status: open`, `current_handler_role: VPO`
* A `ticket_trail` entry of `type: message` is created with the Reason as the initial message body
* Any attachments are saved to `ticket_attachments` linked to this first trail entry

---

# 🔐 PERMISSION MATRIX

| Action | Branch | VPO | CAD |
|---|---|---|---|
| Create ticket | ✅ | ❌ | ❌ |
| View own tickets | ✅ | — | — |
| View VPO open queue | ❌ | ✅ | ❌ |
| View CAD open queue | ❌ | ❌ | ✅ |
| Accept ticket | ❌ | ✅ | ✅ |
| Submit reply (always) | ✅ | ✅ | ✅ |
| Submit to CAD | ❌ | ✅ | ❌ |
| Close ticket (Auto or Immediate) | ❌ | ✅ | ✅ |
| Auto-close (system) | — | — | SYSTEM only |

---

# 🎨 UX & VISUAL REQUIREMENTS

* **No page reloads** — all interactions via modals and JS state
* **Smooth animations** — expand/collapse trail cards with CSS transitions
* **Consistent design language** — clean, card-based, professional
* **DateTime always visible** on every trail card header (not just in expanded view)
* **Mobile-aware** — modal should be scrollable on smaller screens
* **Loading states** — show skeleton/spinner when fetching ticket trails
* **Empty states** — show helpful message when a section has no tickets
* **Error handling** — show inline error if submit fails, do not close modal
* **Automation trail cards** — visually distinct from user messages (⚙️ icon, italic/gray style)

---

# 🧪 EDGE CASES TO HANDLE

1. **Two VPO users try to accept the same ticket simultaneously** — use DB transaction + check `assigned_to IS NULL` before accepting; return error if already claimed
2. **Branch submits a reply on a closed ticket** — Backend should block reply on `status = 'closed'`; hide footer for Branch on closed tickets only
3. **CAD auto-close_at is set very short** — system job still handles it correctly
4. **VPO closes without ever sending to CAD** — `cad_owner` remains NULL; ticket appears only in VPO Closed section
5. **Ticket has no trails yet** — show empty timeline with a placeholder card
6. **Large file attachment** — validate file size before upload; recommend max 10MB per file, configurable
7. **Partner search returns no results** — show "No partner found" state with option to type manually or contact admin

---

# 🗂️ RECOMMENDED FILE STRUCTURE

```
/support-ticket
  /create-ticket
    create-ticket.php
    create-ticket.css

  /branch-ticket
    index.php
    branch-ticket.css

  /vpo-ticket
    index.php
    vpo-ticket.css

  /cad-ticket
    index.php
    cad-ticket.css

  /admin-ticket
    index.php
    admin-ticket.css

  /controllers
    /branch
      create-ticket.php
      reply-ticket.php        ← Branch Submit

    /vpo
      accept-ticket.php
      submit-ticket.php       ← handles Submit (reply) and Submit to CAD
      close-ticket.php        ← handles Auto Close and Close Immediately

    /cad
      accept-ticket.php
      submit-ticket.php       ← handles Submit (reply)
      close-ticket.php        ← handles Auto Close and Close Immediately

    /admin
      view-tickets.php

    /attachments
      upload.php
      download.php            ← streams BLOB back with correct headers

    /system
      auto-close.php          ← called by cron

  /cron
    auto-close.php            ← cron entry: */2 * * * * php /path/to/cron/auto-close.php

  /includes
    db.php                    ← PDO connection
    auth.php                  ← session/role check
    helpers.php               ← ticket_number_gen(), format_date(), etc.
    automation.php            ← fires system trail entries

  /components
    ticket-card.php           ← reusable card HTML
    trail-card.php            ← reusable trail timeline card (always shows DateTime)
    ticket-modal.php          ← base modal shell (role fills footer)
    reply-footer.php          ← attach + message + submit footer
    close-modal.php           ← Auto Close / Close Immediately modal

  /assets
    /css
      shared.css              ← variables, reset, card base, badges, modal base
      branch.css
      vpo.css
      cad.css
      admin.css
    /js
      modal.js
      trail.js
      reply.js
      close.js
```

---

# 🚀 FINAL EXPECTATION

The system must:

* Feel like a **professional, structured ticketing platform**
* Enforce all role permissions at both **UI and API level**
* Fully reflect database state in the UI at all times
* Support the full ticket lifecycle:

```
Create (Branch) → Accept (VPO) → [Reply ↔ Branch] → Submit to CAD
                                      ↓
                               Accept (CAD) → [Reply ↔ Branch] → Close (Auto or Immediate)
                                      ↑
                     VPO can also Close directly (Auto or Immediate) without CAD
```

* Store a **complete, immutable audit trail** for every action
* Every trail card shows **DateTime** for full transparency
* Automation messages fire on key state changes (transfer, accept, close, resolve)
* Be extensible — future roles or statuses should require minimal schema changes