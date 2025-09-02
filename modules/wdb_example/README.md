# wdb_example

WDB Example submodule for managing example data lifecycle.

- Admin UI: WDB Dashboard > Configuration > WDB Example Settings
- Drush: `wdb-example:mark` (`wdbex:mark`)

## English

### Overview
This submodule lets you mark example entities for safe purge, control whether example data is removed on module uninstall, and run a confirmed purge from the admin UI.

### Features
- Provenance tracking with a dedicated map table (entity type + entity UUID)
- Idempotent marking of existing example data
- Selective purge (dependency-aware deletion order)
- Config: purge on uninstall (default: disabled)
- Admin UI actions: Mark, Wipe & Mark, Purge (with confirmation)
- Drush command to bulk-mark existing data

### Admin UI
- Path: `/admin/wdb/settings/example`
- Access: `administer wdb configuration`
- Actions:
  - Mark existing samples: scan and record example entities into the map
  - Wipe & Mark: reset the map then mark from scratch
  - Run Purge now: opens a confirmation screen showing counts before deletion
- Setting:
  - Purge example data on uninstall: when enabled, tracked entities are deleted during module uninstall; a concise summary is logged

### Drush
- Command: `wdb-example:mark` (alias: `wdbex:mark`)
- Options:
  - `--wipe-map`: Drop and recreate the tracking table before marking
  - `--dry-run`: Show what would be marked without writing
- Examples:
  - `drush wdb-example:mark`
  - `drush wdb-example:mark --wipe-map`
  - `drush wdb-example:mark --dry-run`

### Uninstall behavior
- If the setting “Purge example data on uninstall” is enabled, uninstall will delete tracked entities and log a short summary (counts by type, success/failure totals).

### Logs
- Purge operations log concise summaries to help audit deletions during uninstall and on-demand purge.

---

## 日本語

### 概要
このサブモジュールは、サンプルデータ（例示用エンティティ）のライフサイクル管理を提供します。既存データのマーキング、アンインストール時の削除可否の制御、確認付きの即時パージが行えます。

### 機能
- 由来追跡（専用マップテーブルに entity type と entity UUID を記録）
- 既存サンプルデータの冪等なマーキング
- 選択的パージ（依存関係を考慮した削除順序）
- 設定：アンインストール時にサンプルデータを削除（既定：無効）
- 管理UIのアクション：マーキング、全消去してからマーキング、パージ（確認画面あり）
- 既存データ一括マーキングの Drush コマンド

### 管理UI
- パス：`/admin/wdb/settings/example`
- アクセス権限：`administer wdb configuration`
- アクション：
  - 既存サンプルのマーキング：対象のエンティティをマップに記録します
  - 全消去してからマーキング：マップを初期化してから再マーキングします
  - 今すぐパージ：削除前に件数を表示する確認画面を経由して実行します
- 設定：
  - アンインストール時にサンプルを削除：有効化すると、モジュールのアンインストール時に追跡中のエンティティを削除し、要約ログを出力します。

### Drush
- コマンド：`wdb-example:mark`（エイリアス：`wdbex:mark`）
- オプション：
  - `--wipe-map`：マーキング前にマップテーブルを再作成します
  - `--dry-run`：書き込みを行わず、対象件数のみ表示します
- 使用例：
  - `drush wdb-example:mark`
  - `drush wdb-example:mark --wipe-map`
  - `drush wdb-example:mark --dry-run`

### アンインストール時の挙動
- 設定「アンインストール時にサンプルを削除」を有効にしている場合、アンインストール処理で追跡中のエンティティを削除し、型別件数と成功/失敗の要約をログに出力します。

### ログ
- パージ実行時には要約ログ（件数など）を出力し、作業の監査に役立ちます。
