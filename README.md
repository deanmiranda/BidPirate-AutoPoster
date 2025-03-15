
# 📦 Auction Blog Post Importer (Apt & Nimble LLC)

**Version:** 1.1  
**Author:** Dean Miranda  

---

## 📝 Description  
This WordPress plugin **fetches auction listings** from a **Google Sheet CSV** and **creates WordPress blog posts** for each listing.  
It supports **automatic hourly sync** (via cron) and **manual sync** through the WordPress Admin menu.  

The plugin ensures:  
- ✅ New auction listings are added as new blog posts  
- ⚠️ Existing posts are flagged for review if auction data changes  
- 🚫 No automatic overwrites of manually edited posts  

---

## ⚙️ Features  
- **Hourly automatic sync** of data from a published Google Sheet CSV  
- **Manual sync** via Admin panel (`BidBlender Posts` menu)  
- **Duplicate prevention** based on a unique post identifier  
- **Review flagging** if auction status differs between sheet and WordPress  
- **Custom meta fields** stored for each auction  

---

## ✅ How It Works  
1. The plugin fetches a CSV from a **Google Sheet** (`published as CSV`)  
2. Each row is parsed and compared to existing posts based on a unique identifier  
3. If no match is found, a **new blog post** is created  
4. If a post **exists**, and the `Status` column has changed, the post is **flagged for review**  
5. No automatic updates to existing posts are made (to protect manual edits by users)  

---

## 🗂️ Google Sheet Requirements  
### 1. The Google Sheet **must be published as a CSV**
- Go to **File > Share > Publish to web > CSV**
- Use the generated **CSV link** in the plugin settings (`$csv_url`)

### 2. Required Columns (headers):  
| Column Name | Description                | Notes                     |
|-------------|----------------------------|---------------------------|
| `Address`   | Address of the property     | **MUST remain unchanged!** (used as a primary identifier)  
| `Status`    | Status of the auction       | Compared to trigger a review flag  
| `Auctioneer`| Name of the auctioneer      | Optional field for info  
| `City`      | City of the auction         | Optional field for info  
| `State`     | State of the auction        | Optional field for info  
| `Zip`       | Zip code                    | Optional field for info  
| `Deposit`   | Required deposit amount     | Optional field for info  
| `Date`      | Auction date                | Helps create unique ID  
| `Time`      | Auction time                | Helps create unique ID  
| `Listing Link` | Link to the auction listing | Optional  
| `Terms`     | Auction terms               | Optional  
| `Image`     | Link to an image (optional) | Optional  

---

## 🚩 Flags & Sync Logic  
- Posts are **flagged for review** when the `Status` value differs from the last sync.  
- Flagged posts are marked with a **meta key** `needs_sync_review = true`  
- Manual review is required before syncing updates to flagged posts  
- **No automatic content overwrites** on flagged posts  
- Review flags are displayed in the sync report after each manual sync  

---

## 🛠️ Customization Notes  
- If the **column names change**, you must update the plugin logic  
  - Specifically in the `sync_auction_data_from_google_sheet()` function  
- New columns can be added by:  
  - Extending the **post content generation block**  
  - Adding new **meta fields** as needed  
- The `Address` field **should not change**  
  - Changing it will create a **new post** instead of updating or flagging the existing one  
  - This is because the **unique identifier** for each post is built using:  
    ```
    Address + Zip + Date
    ```  

---

## ⏱️ Cron Details  
- Runs hourly by default  
- Uses `wp_schedule_event()` on `'auction_import_cron'`  
- Manual sync via `BidBlender Posts` menu  

---

## ⚠️ Limitations  
- Google Sheet **must** match the column structure defined  
- Only `Status` is compared for review flagging—additional comparisons require enhancements  
- If the sheet structure changes, the plugin **must be updated**  

---

## 📋 To-Do / Future Enhancements  
- Add automated updates after review approval  
- Add admin dashboard to manage `needs_sync_review` posts  
- Add support for different sheets / multiple feeds  
- Notification system for flagged posts  
- Custom post types or categories for auctions  
- Post editor links in admin sync report  

---

## 🚀 Installation  
1. Clone or download the repo  
2. Zip the folder  
3. Upload the zip file via **WordPress Admin > Plugins > Add New > Upload Plugin**  
4. Activate  
5. Add your Google Sheet CSV URL to the plugin file (`$csv_url` variable)  
6. Done!  

---

## 🧰 Developer Notes  
- Code can be extended to support WooCommerce or CPTs  
- Replace the **`$csv_url`** with your published Google Sheet link  
- Use hooks/filters if integrating into larger systems  

---

## 🔥 Author  
Dean Miranda | Apt & Nimble LLC  
