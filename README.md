# AI Image Gallery

AI 圖片管理系統，用於展示和管理 AI 生成的圖片，支援關鍵詞搜尋和詳細參數記錄。

## 功能特色

- 圖片管理
  - 支援上傳 JPEG、PNG、GIF 和 WebP 格式圖片
  - 自動生成縮圖以提升載入速度
  - 支援 R18 內容標記和過濾
  - 詳細的圖片資訊記錄

- 關鍵詞系統
  - 支援多關鍵詞搜尋
  - 關鍵詞自動建議
  - 可點擊的關鍵詞標籤

- 使用者介面
  - 響應式設計
  - 直覺的操作流程
  - 完整的繁體中文介面

## 系統需求

- PHP 7.4 或更高版本
- PHP GD 擴展（用於圖片處理）
- SQLite 3
- Web 伺服器（Apache 或 Nginx）

## 安裝步驟

1. 複製專案
```bash
git clone https://github.com/your-username/ai-image-gallery.git
cd ai-image-gallery
```

2. 設定目錄權限
```bash
chmod 777 assets/uploads
chmod 777 assets/thumbnails
chmod 777 database
```

3. 設定 Web 伺服器
   - 將網站根目錄指向 `public` 資料夾
   - 確保 PHP 可以正確執行

4. 初始化
   - 系統會在首次訪問時自動建立資料庫
   - 資料庫檔案將建立在 `database/aishow.db`

## 目錄結構

```
ai-image-gallery/
├── assets/
│   ├── thumbnails/    # 縮圖存放目錄
│   └── uploads/       # 上傳圖片存放目錄
├── database/          # 資料庫目錄
│   └── schema.sql     # 資料庫結構定義
├── public/            # 網站根目錄
│   ├── api/          # API 端點
│   ├── css/          # 樣式表
│   ├── js/           # JavaScript 檔案
│   └── *.php         # PHP 頁面
└── src/              # PHP 源碼
    ├── config.php    # 設定檔
    └── Database.php  # 資料庫操作類
```

## 使用說明

1. 上傳圖片
   - 點擊首頁右上角的「新增圖片」按鈕
   - 選擇圖片檔案
   - 填寫類型（SD/Comfy）
   - 新增關鍵詞
   - 填寫詳細資訊
   - 如需要可標記為 R18 內容

2. 搜尋圖片
   - 在搜尋框輸入關鍵詞
   - 可以使用建議的關鍵詞
   - 可以新增多個關鍵詞
   - 可選擇是否顯示 R18 內容

3. 管理圖片
   - 點擊圖片查看詳細資訊
   - 可以編輯圖片資訊
   - 可以刪除圖片
   - 可以點擊關鍵詞進行相關搜尋

## 注意事項

- 請確保上傳目錄和資料庫目錄具有適當的寫入權限
- 建議定期備份 `database/aishow.db` 檔案
- 上傳的圖片會自動生成縮圖，原圖保持不變
- R18 內容預設不會顯示在首頁，需要手動開啟顯示

## 授權

MIT License
