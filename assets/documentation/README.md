# Alpha Insights Documentation

Complete user documentation for Alpha Insights Pro - Intelligent Profit Reports for WooCommerce.

## Overview

### 📊 Documentation Statistics

- **Total Files:** 88 HTML documentation files
- **Total Word Count:** ~165,000+ words
- **Coverage:** All user-facing features + complete developer hooks reference
- **Format:** Static HTML files for easy management
- **Target Audience:** WordPress store owners (non-developers) + Developers section

### 📁 Directory Structure

```
/assets/documentation/alpha-insights/
├── 00_getting-started/ (8 HTML files)
├── 01_license/ (1 HTML file)
├── 02_settings/ (4 HTML files)
├── 03_report-builder/ (5 HTML files + 2 subdirectories)
│   ├── report-manager/ (2 HTML files)
│   └── widgets/ (10 HTML files)
├── 04_cost-of-goods-manager/ (3 HTML files)
├── 05_expense-manager/ (4 HTML files)
├── 06_website-traffic/ (3 HTML files)
├── 07_integrations/
│   ├── facebook/ (3 HTML files)
│   └── google-ads/ (3 HTML files)
├── 08_additional-features/
│   ├── custom-order-costs/ (2 HTML files)
│   ├── custom-product-costs/ (2 HTML files)
│   ├── multi-currency-conversion/ (2 HTML files)
│   └── webhooks/ (2 HTML files)
├── 09_faqs/ (12 HTML files)
├── 10_performance/ (3 HTML files)
└── 11_developers/
    ├── overview/ (1 HTML file)
    ├── filters/ (17 HTML files - Complete filter reference)
    ├── functions/ (2 HTML files)
    └── classes/ (1 HTML file)
```

### 🎯 Coverage by Feature

#### Core Features (100% Documented)
- ✅ Installation and setup
- ✅ License activation and management
- ✅ Product cost management
- ✅ Order profit calculations
- ✅ Expense tracking
- ✅ Report creation and management
- ✅ All widget types
- ✅ Report filtering and customization
- ✅ Data export (PDF, CSV, Excel)
- ✅ Scheduled reports

#### Integrations (100% Documented)
- ✅ Facebook Ads connection and tracking
- ✅ Google Ads connection and tracking
- ✅ Webhook setup and examples
- ✅ Traffic analytics and UTM tracking

#### Advanced Features (100% Documented)
- ✅ Custom order costs
- ✅ Custom product costs
- ✅ Multi-currency support
- ✅ HPOS compatibility
- ✅ Performance optimization
- ✅ Database maintenance

#### Developer Features (100% Documented)
- ✅ All filters (16 filters fully documented)
- ✅ Cost & profit calculation filters (7 filters)
- ✅ Menu & navigation filters (4 filters)
- ✅ Price & currency filters (2 filters)
- ✅ Report & data filters (2 filters)
- ✅ WooCommerce compatibility filters (4 filters)
- ✅ Core functions (2 documented)
- ✅ Key classes (1 documented)
- ✅ Code examples and use cases for each filter

### 📋 File Format

Each HTML file contains:
- **One h2 element** as the main heading/title
- **HTML content** for the rest of the documentation
- **No metadata** - pure HTML content for easy editing

Example structure:
```html
<h2>Main Document Title</h2>
<p>Introduction paragraph...</p>
<h3>Section Heading</h3>
<p>Content...</p>
```

### ✨ Documentation Features

#### User-Friendly Content
- Clear, conversational tone
- Step-by-step instructions
- Real-world examples
- Troubleshooting sections
- Best practices throughout

#### Easy to Manage
- Pure HTML format for simple editing
- No JSON parsing or validation needed
- Direct content editing
- Hierarchical folder structure

#### Comprehensive Coverage
- What, why, and how for every feature
- Visual examples and code snippets
- Common use cases and scenarios
- Error handling and troubleshooting

### 🎓 Documentation by User Type

#### Store Owners (Non-Technical)
- Getting Started section
- FAQs section
- Settings guides
- Report Builder guides
- Integration guides

#### Marketing Managers
- Campaign tracking guides
- Traffic analytics
- UTM tracking
- ROAS optimization

#### Developers
- Developer introduction
- Complete filter reference (16 filters)
- Function reference
- Class reference
- Extensive code examples with real-world use cases
- WooCommerce compatibility notes

## 🔍 Quick Find

### Most Important Documents
1. `first-steps-after-installation.html` - Quick start guide
2. `how-alpha-insights-works.html` - Core concepts
3. `understanding-your-first-report.html` - Report interpretation
4. `creating-custom-reports.html` - Report building
5. `activate-your-license.html` - License activation
6. `00-filters-index.html` - Complete developer filter reference

### Most Common Questions
- How to add product costs
- Understanding negative profit
- What is a good profit margin
- How to handle refunds
- Connecting Facebook/Google Ads

## 🚀 Usage

These HTML files are:
- Loaded dynamically via AJAX in the help modal
- Displayed in modal/sidebar documentation viewers
- Searchable by title and content
- Used for in-app help system
- Easy to edit and maintain

## 📝 Maintenance

### Adding New Documentation
1. Create new .html file in appropriate directory
2. Start with a single `<h2>` element for the title
3. Add content using standard HTML markup
4. Include internal links to related docs using relative paths or full paths from alpha-insights root
5. Follow the folder naming convention (e.g., `00_getting-started`)

### Updating Existing Documentation
1. Locate file by filename in the appropriate directory
2. Edit HTML content directly
3. Maintain the single h2 header structure
4. Add new examples or troubleshooting as needed
5. Update internal links if filenames change

## 🔗 Integration Points

Documentation references:
- External links: wpdavies.dev (support, account, main site)
- Internal links: Relative paths within documentation structure
- Code examples: Actual filter and function names from codebase
- Widget configurations: Real settings from React components
- Documentation served from: /assets/documentation/alpha-insights/

## ✅ Quality Assurance

All documentation:
- ✓ Based on actual code (not assumptions)
- ✓ Tested configurations and examples
- ✓ Accurate widget settings from dataMapping.js
- ✓ Real filter names from includes/ files
- ✓ Verified function signatures
- ✓ Cross-referenced related documentation

## 📞 Support

Documentation questions or suggestions:
- Email: support@wpdavies.dev
- Website: https://wpdavies.dev/contact-us/

---

**Documentation Version:** 1.1.0  
**Last Updated:** October 2025  
**Plugin Version:** Alpha Insights Pro 4.8.0+  
**Author:** WP Davies  
**License:** Proprietary

### Recent Updates

#### October 2025 - Developer Filter Documentation
- ✅ Added complete filter reference (16 filters documented)
- ✅ Added filter index with navigation and examples
- ✅ Documented all cost calculation filters
- ✅ Documented all menu customization filters
- ✅ Documented price and currency filters
- ✅ Documented report and caching filters
- ✅ Documented WooCommerce compatibility filters
- ✅ Added extensive code examples for each filter
- ✅ Included performance notes and best practices


