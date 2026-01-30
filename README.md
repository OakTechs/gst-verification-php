# GST Verification PHP

This project provides a simple, PHP-based solution for verifying GSTINs (Goods and Services Tax Identification Numbers). It includes an API endpoint (`api.php`) for programmatic access and a web interface (`index.php`) for manual verification.

## Features

-   **GSTIN Validation**: Checks the format and validity of a given GSTIN.
-   **GSTIN Information Retrieval**: (To be implemented/expanded) Fetches details associated with a valid GSTIN.
-   **Session Management**: Uses `sessions.json` for temporary storage of verification requests or results.

## Installation

To set up this project, follow these steps:

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/your-username/gst-verification-php.git
    cd gst-verification-php
    ```
    *(Note: Replace `https://github.com/your-username/gst-verification-php.git` with the actual repository URL if available, or just state that the files should be placed in a web server directory.)*

2.  **Web Server Setup**:
    Ensure you have a PHP-compatible web server installed (e.g., Apache, Nginx, WAMP, XAMPP). Place the project files in your web server's document root or a virtual host directory.

    For example, if using Apache on Windows (like WAMP), place the `gst-verification-php` folder in `C:\wamp64\www\`.

3.  **Permissions**:
    Ensure the web server has write permissions to `sessions.json` if it's used for storing data.

## Usage

### Web Interface

Access the main verification page by navigating to `http://localhost/gst-verification-php/index.php` in your web browser (adjust the URL based on your web server configuration).

### API Endpoint

You can interact with the API directly by sending POST requests to `http://localhost/gst-verification-php/api.php`.

**Request Method**: `POST`
**Content-Type**: `application/json`

**Example Request Body**:

```json
{
    "gstin": "27XXXXX1234A1Z5"
}
```

**Example Response (Success)**:

```json
{
    "status": "success",
    "gstin": "27XXXXX1234A1Z5",
    "isValid": true,
    "details": {
        "legalName": "Example Company Name",
        "tradeName": "Example Trade Name",
        "address": "123, Example Street, City, State - 123456",
        "status": "Active"
    }
}
```

**Example Response (Error/Invalid GSTIN)**:

```json
{
    "status": "error",
    "message": "Invalid GSTIN format or unable to verify."
}
```

## Project Structure

-   `api.php`: Handles API requests for GSTIN verification.
-   `index.php`: Provides the web-based user interface.
-   `sessions.json`: Stores session data or temporary verification results.
-   `README.md`: This file.

## License
