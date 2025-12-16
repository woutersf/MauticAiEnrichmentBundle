# MauticAiEnrichmentBundle

> ## ⚠️ **PROOF OF CONCEPT - USE AT YOUR OWN RISK** ⚠️
>
> **THIS IS A PROOF OF CONCEPT (POC) IMPLEMENTATION**
>
> - **NOT FOR PRODUCTION USE**
> - **NO WARRANTY OR SUPPORT PROVIDED**
> - **USE AT YOUR OWN RISK**
> - **MAY CONTAIN BUGS AND SECURITY ISSUES**
> - **API COSTS MAY APPLY** - This plugin makes multiple AI API calls which can incur significant costs
>
> This is an experimental implementation to demonstrate AI-powered company data enrichment capabilities.
> It has not been tested thoroughly and should not be used in production environments.

---

## Overview

MauticAiEnrichmentBundle is a Mautic plugin that uses AI to automatically enrich company data by searching the web and extracting relevant information.

## Features

### Inline Autofill
- **"Autofill ✨" links** appear next to empty company fields
- Click to get AI-powered suggestions
- Choose from multiple options returned by AI
- Automatically fills fields without saving

### Supported Fields
1. **Company Email** - Finds contact email addresses
2. **Company Website** - Finds official website URLs
3. **Company Address** - Finds and parses complete addresses (street, city, postal code, country)
4. **Company Phone** - Finds contact phone numbers
5. **Company Employees** - Finds number of employees
6. **Company Description** - Generates company descriptions

### AI Architecture

**Two-Tier AI System:**

1. **Level 1: Assistant AI**
   - Decides which URLs to search
   - Has access to `web_search` tool
   - Coordinates the enrichment process
   - Makes up to 7 search attempts

2. **Level 2: Site Fetcher AI**
   - Analyzes web content fetched by Assistant AI
   - Extracts specific information
   - Returns 2-5 options for user selection

### Smart Features

- **Dynamic Field Monitoring** - Autofill links appear/disappear based on field content
- **CSV Parsing** - Properly handles comma-separated options with quoted strings
- **Address Intelligence** - Automatically splits addresses into separate fields
- **Multi-Format Support** - Handles both comma and slash-separated address formats
- **Field Type Detection** - Works with inputs, textareas, and select dropdowns (including Chosen)
- **Unknown Handling** - Shows "Information not found" instead of "UNKNOWN" buttons

## Requirements

- Mautic installation
- **MauticAiConnectionBundle** - For LiteLLM AI connections
- **MauticAiLogBundle** - For AI operation logging
- Active AI API key configured in MauticAiConnectionBundle
- PHP 8.0+

## Installation

1. Copy the bundle to `docroot/plugins/MauticAiEnrichmentBundle/`
2. Clear cache: `php bin/console cache:clear`
3. Reload plugins: `php bin/console mautic:plugins:reload`
4. Configure AI model and prompts in Settings → Configuration → AI Enrichment

## Configuration

Navigate to **Settings → Configuration → AI Enrichment** to configure:

- **AI Model** - Select from available models (default: gpt-4)
- **Assistant Prompt** (Level 1) - System prompt for the search coordinator
- **Fetcher Prompt** (Level 2) - System prompt for content analyzer

## Usage

### Inline Autofill

1. Go to any company edit/new page: `/s/companies/edit/{id}`
2. Look for **"Autofill ✨"** links next to empty fields
3. Click the link to start AI enrichment
4. Wait for AI to search (modal shows loading)
5. Choose from 2-5 options presented as buttons
6. Selected value fills the field (does not auto-save)
7. Save the company manually when ready

### AI Log

View all AI enrichment operations at: `/s/ailog`

- Last 1000 log entries
- Color-coded by level (ERROR, WARNING, INFO, DEBUG)
- Shows newest entries first
- Displays API requests, responses, tool calls, and results

## Technical Details

### File Structure

```
MauticAiEnrichmentBundle/
├── Config/
│   └── config.php                 # Routes, services, parameters
├── Controller/
│   └── EnrichmentController.php   # Modal, options, save endpoints
├── Service/
│   ├── EnrichmentService.php      # Core AI orchestration
│   ├── EnrichmentTypeRegistry.php # Field configurations
│   └── WebFetcherService.php      # HTTP client for web fetching
├── EventListener/
│   ├── ButtonSubscriber.php       # Inject "Enrich with AI" button
│   ├── ConfigSubscriber.php       # Register config form
│   └── AssetSubscriber.php        # Inject JavaScript
├── Assets/js/
│   ├── enrichment.js              # Modal-based enrichment (legacy)
│   └── inline-enrichment.js       # Inline autofill functionality
├── Form/Type/
│   └── ConfigType.php             # Configuration form
├── Resources/views/
│   ├── Enrichment/
│   │   ├── modal.html.twig        # Modal UI (legacy)
│   │   └── result_table.html.twig # Result display
│   └── FormTheme/Config/
│       └── _config_aienrichmentconfig_widget.html.twig
├── Integration/
│   ├── AiEnrichmentIntegration.php
│   └── Support/ConfigSupport.php
└── Translations/en_US/
    └── messages.ini
```

### Key Routes

- `/s/companies/enrichment/options/{companyId}` - Get AI options (AJAX)
- `/s/companies/enrichment/save/{companyId}` - Save enriched data (AJAX)
- `/s/companies/enrichment/modal/{companyId}` - Modal view (legacy)

### How It Works

1. User clicks "Autofill ✨" on empty field
2. JavaScript sends AJAX request with company name
3. **Assistant AI (Level 1)** receives request with `web_search` tool
4. Assistant decides which URLs to search (up to 7 iterations)
5. For each URL:
   - `web_search` tool fetches content
   - **Site Fetcher AI (Level 2)** analyzes content
   - Extracted info returned to Assistant
6. Assistant returns 2-5 options
7. JavaScript displays options as clickable buttons
8. User selects option → field is filled

### Address Parsing

Supports two formats:

**Comma format:**
```
"Moutstraat 60, 9000 Ghent, Belgium"
```
Parsed to:
- `companyaddress1`: "Moutstraat 60"
- `companyzipcode`: "9000"
- `companycity`: "Ghent"
- `companycountry`: "Belgium"

**Slash format:**
```
"Belgium / 9000 / Ghent / Main Street 123 / Building A"
```
Parsed to:
- `companycountry`: "Belgium"
- `companyzipcode`: "9000"
- `companycity`: "Ghent"
- `companyaddress1`: "Main Street 123"
- `companyaddress2`: "Building A"

## Performance & Costs

⚠️ **Important Cost Considerations:**

- Each enrichment triggers **multiple AI API calls**
- Up to **7 iterations** per enrichment attempt
- Each iteration may call **2 AI models** (Assistant + Fetcher)
- Costs can accumulate quickly with frequent use
- Monitor your AI API usage and costs closely

**Typical API calls per enrichment:**
- 1-7 calls to Assistant AI (Level 1)
- 1-7 calls to Site Fetcher AI (Level 2)
- Total: 2-14 API calls per field enrichment

## Limitations

- Depends on web content availability and quality
- May not find information for small/private companies
- Results accuracy depends on AI model capabilities
- No caching - each request triggers new API calls
- No rate limiting implemented
- No batch processing

## Troubleshooting

### Autofill link not appearing
- Check if field is truly empty
- Clear browser cache
- Ensure JavaScript is loaded: check browser console

### "Information not found" message
- Company may not have web presence
- Try different field (e.g., website before email)
- Check AI log at `/s/ailog` for details

### Enrichment failing
- Check AI API credentials in MauticAiConnectionBundle
- Verify API has available credits/quota
- Check `/s/ailog` for error messages
- Review Mautic logs in `var/logs/`

### Address not parsing correctly
- Update prompts in Settings → Configuration
- Check format returned in `/s/ailog`
- Modify JavaScript parsing if needed

## Security Considerations

⚠️ **This is a POC - Security has not been thoroughly reviewed:**

- Web content is fetched from arbitrary URLs
- AI responses are not sanitized before display
- No input validation on company names
- No CSRF protection on AJAX endpoints
- Tool calling allows AI to make HTTP requests
- Logs may contain sensitive information

**Do not use in production without security review and hardening.**

## Development

### Adding New Fields

1. Add to `fieldConfig` in `Assets/js/inline-enrichment.js`
2. Add to `getAvailableTypes()` in `Service/EnrichmentTypeRegistry.php`
3. Clear cache

### Modifying Prompts

Edit in Settings → Configuration → AI Enrichment, or modify defaults in `Config/config.php`:
- `ai_enrichment_assistant_prompt` - Level 1 AI
- `ai_enrichment_fetcher_prompt` - Level 2 AI

### Debugging

- Check `/s/ailog` for AI operation logs
- Check `var/logs/ai.log` for raw log file
- Enable debug logging in Mautic configuration
- Use browser developer tools to inspect AJAX requests

## Credits

- **Author**: Frederik Wouters
- **Version**: 1.0.0 (POC)
- **Mautic**: Open source marketing automation
- **LiteLLM**: AI model proxy for unified API access

## License

This is proof-of-concept code. No license specified.

---

> ## ⚠️ **REMINDER: THIS IS A POC** ⚠️
>
> **DO NOT USE IN PRODUCTION**
>
> This code is provided as-is for demonstration and experimentation purposes only.
> No warranty, support, or guarantees of any kind are provided.
