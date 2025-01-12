# YOURLS Referrer Stats Plugin

This plugin allows you to track and retrieve statistics based on custom referral parameters (`r_param`) for shortened URLs in your YOURLS installation. It provides an API endpoint to query and return paginated statistics for `r_param` values, including total clicks for each referrer.

## Features

 - Tracks a custom `r_param` column in the YOURLS log table.
 - Adds API support to retrieve stats for specific referral parameters.
 - Supports paginated responses.
 - Handles optional filtering by r_param values.
 - Default handling for direct clicks (when `r_param` is not provided).
 - Added analytics on shorturl detail page (admin panel).

## Use Cases

1. **Marketing Campaign Tracking**  
   Use the `r_param` to track the effectiveness of different marketing campaigns. For example:
   - `?r=facebook-ad` for links shared via Facebook ads.
   - `?r=email-newsletter` for links included in email campaigns.  
   Retrieve detailed stats for each campaign using the API.

2. **Channel Attribution** (my usecase)  
   Identify which platforms or channels drive the most traffic to your links by assigning unique `r_param` values, such as:
   - `?r=social-media`
   - `?r=qr-code`


## How It Works

1. Database Changes:
    - Adds a new column `r_param` (default value: `direct`) to the YOURLS log table.
    - Ensures the column is non-nullable and has a database index for optimized queries.

2. API Endpoint:
    - The plugin registers a new YOURLS API action (`ref-stats`).
    - Query stats for a specific shorturl with optional filters for `r_param`.
    - Supports pagination with configurable `per_page` and `page` parameters.

3. Fallback for `r_param`:
    - If no `r_param` is provided, entries are grouped under the direct category.

## Installation
1. Clone and copy the `track-ref-param/` to the `user/plugins/` directory in your YOURLS installation.
2. Activate the plugin from the YOURLS admin interface.
3. On activation, the plugin will:
    - Add the `r_param` column to the YOURLS log table.
    - Set up indexing for the `r_param` column.


## How to Use
1. Adding Referral Parameters:
    - When redirecting a YOURLS link, append ?`r=<value>` to the URL.
    - Example: http://short.url/abc?r=qr-code.

2. Fetching Statistics:
    - Use the API endpoint to retrieve referral stats as shown in the example request.

3. Pagination:
    - Customize results per page and navigate through pages using the per_page and page parameters.

## API Usage

### Request
```sh
curl --location 'http://localhost:8000/yourls-api.php?action=ref-stats&shorturl=2&format=json&per_page=1&r-params=qr-code,widget&page=1&signature=1234'

```
### Response
```json
{
    "status": "success",
    "statusCode": 200,
    "data": [
        {
            "r_param": "qr-code",
            "clicks": 2
        },
        {
            "r_param": "widget",
            "clicks": 1
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 1,
        "total": 2,
        "total_pages": 2
    }
}
```

