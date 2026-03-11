# Logic Essentials: Fluent Forms Addon

**Version:** 1.0.0 (MVP)

**Requires:** Fluent Forms (Free) v6.1+

**Tested up to:** WordPress 6.4+

## Overview

Logic Essentials is a lightweight, high-performance companion add-on for the free version of Fluent Forms. It seamlessly injects conditional logic capabilities directly into the native form builder, allowing you to show or hide fields dynamically based on user input.

Designed with strict database hygiene in mind, this plugin features "The Purge" architecture—ensuring that any field dynamically hidden by the user is completely stripped from the submission payload *before* it ever hits your database.

## Core Features (MVP)

- **Native UI Integration:** Blends perfectly into the Fluent Forms editor sidebar. No clunky external settings pages.

- **Frontend Runtime:** Instantly toggles field visibility in the browser using Vanilla JS and CSS.

- **Standard Operators:** Supports `is`, `is not`, `contains`, and `greater than` logic evaluations.

- **Pre-Insert Data Purge:** Hooks into `fluentform/before_insert_submission` to automatically delete data from conditionally hidden fields, keeping your database clean.

- **Pro Fallback:** Includes conflict-resolution logic. If Fluent Forms Pro is detected, the add-on elegantly disables its active runtime to prevent conflicts.

---

## 🛠️ Installation & Setup Instructions

Since this is a custom plugin currently in development, you will install it manually via a ZIP file.

### Step 1: Package the Plugin

1. Navigate to your project folder: `C:\Users\Owner\Projects\logic-essentials-fluent-forms-addon`.

2. Select all the files inside this folder (the `.php` files, the `includes/` folder, the `assets/` folder, etc.). Do *not* include the raw `fluentform.6.1.20.zip` or the `fluentform` extracted folder we used for the AI's context.

3. Right-click and compress/zip them into a single file named `logic-essentials.zip`.

### Step 2: Install on WordPress

1. Log into your WordPress Admin Dashboard.

2. Ensure the **Fluent Forms (Free)** plugin is installed and activated.

3. Go to **Plugins > Add New Plugin > Upload Plugin**.

4. Upload your `logic-essentials.zip` file, install, and click **Activate**.

---

## 🧪 Initial Testing Protocol

To verify that both the visual frontend and the backend database hooks are functioning correctly, perform the following 3-step quality control test:

### Test 1: Verify the Admin UI Injection

1. Go to **Fluent Forms** in your WordPress dashboard and create a "New Blank Form".

2. Add a **Dropdown** field (Name it "Budget") and give it two options: "Under $1k" and "Over $1k".

3. Add a **Single Line Text** field right below it (Name it "Enterprise Requirements").

4. Click on the "Enterprise Requirements" field to open its settings in the right-hand sidebar.

5. **Expected Result:** You should see your new "Logic Essentials" panel or tab natively injected here. Set a rule: *Show this field IF 'Budget' IS 'Over $1k'*. Save the form.

### Test 2: Verify the Frontend Runtime (JavaScript)

1. Click the **Preview & Design** button at the top of the form builder.

2. When the page loads, the "Enterprise Requirements" text field should be completely invisible.

3. Change the "Budget" dropdown to "Over $1k".

4. **Expected Result:** The text field should instantly appear on the screen without the page reloading. Change it back to "Under $1k" and it should disappear.

### Test 3: Verify "The Purge" (Backend Data Sanitization)

1. While still on the Preview page, set the "Budget" to **Under $1k** (ensuring the text field remains hidden).

2. Right-click the hidden text field area, use your browser's "Inspect Element", and manually type some text into the hidden field's HTML value, then submit the form. (Alternatively, fill it out, *then* change the dropdown to hide it, and submit).

3. Go to **Fluent Forms > Entries** and view the submission you just made.

4. **Expected Result:** The entry should *only* show the "Budget" answer. The data for "Enterprise Requirements" should be completely missing from the database record, proving your `fluentform/before_insert_submission` hook successfully intercepted and purged the hidden data.
