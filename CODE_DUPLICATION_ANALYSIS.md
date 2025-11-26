# 🔍 CODE DUPLICATION & CLEANUP ANALYSIS
**Date:** November 25, 2025  
**Status:** Analysis Complete - Ready for Refactoring

---

## 📊 EXECUTIVE SUMMARY

**Total Duplication Issues Found:** 12 major areas  
**Total Lines of Duplicated Code:** ~450+ lines  
**Severity:** MEDIUM (Code maintenance and bundle size)  
**Priority:** HIGH (These should be refactored for maintainability)

---

## 🎯 CRITICAL DUPLICATIONS TO REMOVE/CONSOLIDATE

### 1. **Password Toggle Function (DUPLICATE)**
**Location:** 
- `index.php` line 278
- `register.php` line 687

**Code:**
```javascript
function togglePassword(fieldId, iconId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
```

**Issue:** Exact same function in TWO files (identical logic)

**Recommendation:** 
- ✅ Create `/assets/js/common.js` with shared functions
- ✅ Remove from register.php
- ✅ Remove from index.php
- ✅ Include in both files: `<script src="./assets/js/common.js"></script>`

---

### 2. **CSS Form Styling (MAJOR DUPLICATE)**
**Location:**
- `index.php` lines 164-210 (45+ lines)
- `register.php` lines 141-150 (10 lines)

**Duplicated Classes:**
```css
.form-container { ... }
.brand-logo { width:72px; height:auto; display:block; margin:0 auto }
.input-field { ... }
.input-field:focus { ... }
.submit-button { ... }
.submit-button:hover { ... }
.error-message { ... }
```

**Issue:** Same styling rules repeated in multiple files

**Recommendation:**
- ✅ Move all to `/assets/css/forms.css`
- ✅ Import in both files: `<link rel="stylesheet" href="./assets/css/forms.css">`
- ✅ Remove inline `<style>` tags

---

### 3. **Inline Style Tags (DUPLICATE)**
**Location:**
- Both `index.php` and `register.php` have identical `<style>` imports

**Duplicated:**
```html
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Inter', sans-serif;
        margin: 0;
        padding: 0;
        background: linear-gradient(to bottom right, #ffffff, #cce0ff);
        background-attachment: fixed;
        background-repeat: no-repeat;
        min-height: 100vh;
    }
    ...
</style>
```

**Issue:** Same CSS repeated

**Recommendation:**
- ✅ Move to external `/assets/css/forms.css`
- ✅ Single import location
- ✅ Reduces HTML file size

---

### 4. **Form Container HTML (DUPLICATE)**
**Location:**
- `index.php` lines 227-236
- `register.php` lines 156-168

**Both have:**
```html
<div class="form-container">
    <div style="text-align: center; margin-bottom: 2rem;">
        <img src="./assets/images/umak3.ico" alt="...">
        <h2>...</h2>
        <p>...</p>
    </div>
    <!-- Form messages -->
</div>
```

**Issue:** Nearly identical wrapper structure (only content differs)

**Recommendation:**
- ✅ Extract to reusable HTML component or PHP include
- ✅ Create `/includes/form_header.php`
- ✅ Pass variables for title, description

---

### 5. **Validation Functions in register.php (INCOMPLETE)**
**Location:** `register.php` lines 334-460

**Duplicated JavaScript Validation Functions:**
```javascript
validateName()      // Used 3+ times
validateEmail()     // Used 2+ times
validatePassword()  // Used 3+ times
validateStudentId() // Used 2+ times
validatePhone()     // Used 2+ times
```

**Issue:** These functions are defined but many are only used on register.php, not on login

**Recommendation:**
- ✅ Move shared validators to `common.js`
- ✅ Keep register-specific validation in register.php only
- ✅ Move basic email/password validation to common.js for both pages

---

### 6. **Backend Validation (DUPLICATE LOGIC)**
**Location:**
- `register.php` lines 28-63 (Backend validation)
- `register.php` lines 334-460 (Frontend validation)

**Duplicated Logic:**
```php
// Backend
if (!preg_match('/^[a-zA-Z\s\-\']+$/', $first_name)) { ... }

// Frontend (exact same regex)
function validateName(name) {
    return /^[a-zA-Z\s\-']+$/.test(name);
}
```

**Issue:** Validation rules defined TWICE - PHP backend AND JavaScript frontend

**Recommendation:**
- ✅ Keep backend validation (security)
- ✅ Keep frontend validation (UX)
- ⚠️ NOTE: This is intentional but should be documented
- ✅ Consider creating validation config file with rules

---

### 7. **Message/Alert Styling (DUPLICATE)**
**Location:**
- `index.php` lines 217-223 (error-message div)
- `register.php` lines 168-178 (success/error message divs)

**Both use similar HTML:**
```html
<div class="error-message">...</div>
<div class="mt-4 bg-red-100 border border-red-400 ...">...</div>
```

**Issue:** Mixed CSS class and Tailwind usage

**Recommendation:**
- ✅ Standardize to Tailwind only (consistent with rest of app)
- ✅ Create Tailwind component for messages
- ✅ Or create reusable message include file

---

### 8. **Logo/Header HTML (DUPLICATE)**
**Location:**
- `index.php` line 229-236
- `register.php` line 158-164

**Both:**
```html
<img src="./assets/images/umak3.ico" alt="...">
<h2>...</h2>
<p>...</p>
```

**Issue:** Same header structure (title/subtitle/logo)

**Recommendation:**
- ✅ Extract to `/includes/form_logo.php`
- ✅ Use: `<?php include 'includes/form_logo.php'; ?>`

---

### 9. **Unused CSS Classes**
**Location:** `index.php` lines 173-214

**Unused classes found:**
```css
.brand-logo { ... }        /* Only used in HTML once, could use inline Tailwind */
.nav-logo { ... }          /* NOT USED ANYWHERE */
.brand-text { ... }        /* NOT USED ANYWHERE */
.top-nav { ... }           /* NOT USED ANYWHERE */
```

**Issue:** Dead code taking up space

**Recommendation:**
- ✅ Delete `.nav-logo` - not used
- ✅ Delete `.brand-text` - not used
- ✅ Delete `.top-nav` - not used
- ✅ Replace `.brand-logo` with Tailwind inline styles

---

### 10. **Input Field Wrapper Code (DUPLICATE)**
**Location:** 
- `register.php` lines 271-292 (password fields with toggle)
- `index.php` lines 253-266 (password field with toggle)

**Both have:**
```html
<div style="position: relative;">
    <input ... style="padding-right: 2.5rem;">
    <button type="button" onclick="togglePassword(...)">
        <i id="..." class="fas fa-eye"></i>
    </button>
</div>
```

**Issue:** Same wrapper pattern repeated for each password field

**Recommendation:**
- ✅ Create reusable HTML component or include
- ✅ OR create PHP function to generate password input
- ✅ Reduces code duplication

---

### 11. **Session Start & Includes (UNNECESSARY DUPLICATION)**
**Location:**
- `register.php` lines 1-4
- `index.php` lines 1-5

**Both have:**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'config/admin_access.php';  // Only index.php needs this
```

**Issue:** Could be moved to autoload or config file

**Recommendation:**
- ⚠️ Keep as is (this is minimal and necessary)
- ✅ Note: `admin_access.php` only needed for index.php (correct)

---

### 12. **Meta Tags & HTML Head (DUPLICATE)**
**Location:**
- Both `index.php` and `register.php` have identical head sections:

```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>...</title>  <!-- Only difference -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
```

**Issue:** Same meta/script/link tags

**Recommendation:**
- ✅ Create `/includes/page_head.php` that accepts title as parameter
- ✅ Use: `<?php include 'includes/page_head.php'; ?>`

---

## 📋 ACTION PLAN (PRIORITY ORDER)

### **PHASE 1: IMMEDIATE (Quick Wins)**
1. ✅ Create `/assets/js/common.js` - Move `togglePassword()` function
2. ✅ Create `/assets/css/forms.css` - Move all form styling
3. ✅ Delete unused CSS classes from `index.php`
4. ✅ Remove inline `<style>` tags from both pages

**Estimated savings:** ~150 lines

---

### **PHASE 2: MEDIUM (Component Extraction)**
5. ✅ Create `/includes/page_head.php` - Shared HTML head
6. ✅ Create `/includes/form_logo.php` - Form header with logo
7. ✅ Create form message component
8. ✅ Create password input wrapper component

**Estimated savings:** ~180 lines

---

### **PHASE 3: LONG-TERM (Architecture)**
9. ⚠️ Consider template system or page builder
10. ⚠️ Move validation rules to config file
11. ⚠️ Consider form builder class/component

**Estimated savings:** ~120 lines (future)

---

## 📊 BEFORE/AFTER ESTIMATES

### **Current State**
- `index.php`: 294 lines
- `register.php`: 707 lines
- **Total:** ~1,001 lines

### **After Cleanup**
- `index.php`: ~150 lines (50% reduction)
- `register.php`: ~480 lines (32% reduction)
- **Total:** ~630 lines (37% reduction)
- **New shared files:** ~250 lines
- **NET savings:** ~120 lines + improved maintainability

---

## ✅ ITEMS SAFE TO REMOVE (No Impact)

| Item | Location | Reason |
|------|----------|--------|
| `.nav-logo` class | index.php L173 | Never used in markup |
| `.brand-text` class | index.php L176 | Never used in markup |
| `.top-nav` class | index.php L178 | Never used in markup |
| Unused `<style>` block | register.php | Should use external CSS |

---

## ⚠️ IMPORTANT NOTES

### **DO NOT REMOVE:**
- Backend validation in `register.php` lines 28-63 (security-critical)
- Session includes (all files need database.php & functions.php)
- Role-based access checks in `index.php` (admin/doctor/nurse logic)

### **KEEP BUT DOCUMENT:**
- Duplicate frontend/backend validation (intentional for security + UX)
- Multiple `.form-container` references (same styling, different structure)

---

## 🔧 REFACTORING EXAMPLES

### Example 1: Extract togglePassword to common.js
```javascript
// assets/js/common.js
function togglePassword(fieldId, iconId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
```

Then in both `index.php` and `register.php`:
```html
<script src="./assets/js/common.js"></script>
<!-- Remove duplicate function definition -->
```

---

### Example 2: Extract Form Header
```php
// includes/form_logo.php
<?php
$form_title = $form_title ?? "Form";
$form_subtitle = $form_subtitle ?? "Complete this form";
?>
<div class="text-center">
    <img src="./assets/images/umak3.ico" alt="UMAK" style="height: 80px; width: auto; margin: 0 auto; display: block;">
    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
        <?= $form_title; ?>
    </h2>
    <p class="mt-2 text-sm text-gray-600">
        <?= $form_subtitle; ?>
    </p>
</div>
```

Then in `register.php`:
```php
<?php
$form_title = "Create your account";
$form_subtitle = "create account to book an appointment";
include 'includes/form_logo.php';
?>
```

---

### Example 3: External CSS Import
```html
<!-- Remove from index.php and register.php -->
<style>
    @import url('...Inter font...');
    .form-container { ... }
    ...
</style>

<!-- Add instead: -->
<link rel="stylesheet" href="./assets/css/forms.css">
```

---

## 📈 METRICS

| Metric | Current | After Cleanup |
|--------|---------|---------------|
| Total Lines | 1,001 | 630 |
| CSS Duplications | 2 | 1 |
| JS Functions (duplicated) | 1 | 0 |
| Form Headers (duplicated) | 2 | 1 |
| HTML Head Blocks | 2 | 1 |
| Bundle Size Reduction | - | ~37% |
| Maintainability | Medium | High |

---

## 🎯 CONCLUSION

**Main Issues:**
1. ✅ `togglePassword()` function duplicated
2. ✅ CSS styling scattered and duplicated  
3. ✅ HTML structure patterns repeated
4. ✅ Unused CSS classes taking space

**Easy Wins:**
- Move `togglePassword()` to `common.js`
- Move CSS to `forms.css`
- Delete unused classes
- Extract form header component

**Next Steps:**
1. Create Phase 1 files (JS, CSS)
2. Update includes in both pages
3. Test thoroughly
4. Document component usage

**Estimated Time:** 30-45 minutes for complete refactoring

---

**Generated:** November 25, 2025  
**Status:** Ready for Implementation
