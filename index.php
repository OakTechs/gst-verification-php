<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Verification API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, button {
            padding: 8px;
            font-size: 16px;
        }
        input[type="text"] {
            width: 300px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
        .refresh-btn {
            background-color: #2196F3;
        }
        .refresh-btn:hover {
            background-color: #1976D2;
        }
        img {
            border: 1px solid #ddd;
            margin-top: 10px;
            max-width: 300px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        .search-history {
            margin-top: 20px;
            border: 1px solid #eee;
            padding: 10px;
            background-color: #f5f5f5;
        }
        .history-item {
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .loading {
            display: none;
            color: blue;
        }
    </style>
</head>
<body>
    <h1>GST Verification API (PHP Version)</h1>
    <p style="color: #666; font-size: 14px;">This is a standalone PHP application - no Python server required!</p>

    <div class="form-group">
        <button onclick="getNewCaptcha()" class="refresh-btn">Get New CAPTCHA</button>
    </div>

    <div id="captchaSection" style="display:none;">
        <div class="form-group">
            <label for="sessionId">Session ID:</label>
            <input type="text" id="sessionId" readonly>
        </div>

        <div class="form-group">
            <label>CAPTCHA Image:</label>
            <img id="captchaImage" alt="CAPTCHA">
        </div>

        <div class="form-group">
            <label for="captchaInput">Enter CAPTCHA Text:</label>
            <input type="text" id="captchaInput" placeholder="Enter CAPTCHA text">
        </div>

        <div class="form-group">
            <label for="gstinInput">Enter GSTIN:</label>
            <input type="text" id="gstinInput" placeholder="Enter GST Number" maxlength="15">
        </div>

        <div class="form-group">
            <button onclick="getGstDetails()">Get GST Details</button>
            <button onclick="clearForm()" class="refresh-btn">Clear Form</button>
        </div>

        <div id="loading" class="loading">Loading... Please wait.</div>
    </div>

    <div id="result"></div>

    <div class="search-history">
        <h3>Recent Searches</h3>
        <div id="historyContainer"></div>
    </div>

    <script>
        let currentSessionId = null;
        let searchHistory = [];

        // Get the base URL for API calls (works for both localhost and any domain)
        const apiBaseUrl = window.location.origin + '/gst-verification-php';

        // Load search history from localStorage if available
        if (localStorage.getItem('gstSearchHistory')) {
            searchHistory = JSON.parse(localStorage.getItem('gstSearchHistory'));
            displayHistory();
        }

        async function getNewCaptcha() {
            try {
                document.getElementById('loading').style.display = 'block';
                const response = await fetch(`${apiBaseUrl}/api.php?endpoint=getCaptcha`);
                const data = await response.json();
                document.getElementById('loading').style.display = 'none';

                if (data.sessionId) {
                    currentSessionId = data.sessionId;
                    document.getElementById('sessionId').value = data.sessionId;
                    document.getElementById('captchaImage').src = data.image;
                    document.getElementById('captchaSection').style.display = 'block';
                    document.getElementById('result').innerHTML = '';

                    // Clear previous inputs
                    document.getElementById('captchaInput').value = '';
                    document.getElementById('gstinInput').value = '';
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('result').innerHTML = `<div class="error">Error getting CAPTCHA: ${error.message}</div>`;
            }
        }

        async function getGstDetails() {
            const captchaText = document.getElementById('captchaInput').value.trim();
            const gstin = document.getElementById('gstinInput').value.trim().toUpperCase();
            const sessionId = document.getElementById('sessionId').value;

            if (!captchaText || !gstin || !sessionId) {
                document.getElementById('result').innerHTML = '<div class="error">Please fill in all fields</div>';
                return;
            }

            try {
                document.getElementById('loading').style.display = 'block';
                const response = await fetch(`${apiBaseUrl}/api.php?endpoint=getGSTDetails`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        sessionId: sessionId,
                        GSTIN: gstin,
                        captcha: captchaText
                    })
                });

                const data = await response.json();
                document.getElementById('loading').style.display = 'none';

                if (response.ok) {
                    document.getElementById('result').innerHTML = `
                        <div class="success">
                            <h3>GST Details for ${gstin}:</h3>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;

                    // Add to search history
                    addToHistory(gstin, data);
                } else {
                    throw new Error(data.error || 'Server error');
                }
            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('result').innerHTML = `<div class="error">Error getting GST details: ${error.message}</div>`;
            }
        }

        function addToHistory(gstin, details) {
            const historyItem = {
                timestamp: new Date().toLocaleString(),
                gstin: gstin,
                details: details
            };

            searchHistory.unshift(historyItem); // Add to beginning

            // Keep only the last 5 searches
            if (searchHistory.length > 5) {
                searchHistory = searchHistory.slice(0, 5);
            }

            // Save to localStorage
            localStorage.setItem('gstSearchHistory', JSON.stringify(searchHistory));

            displayHistory();
        }

        function displayHistory() {
            const container = document.getElementById('historyContainer');

            if (searchHistory.length === 0) {
                container.innerHTML = '<p>No search history yet.</p>';
                return;
            }

            let html = '';
            searchHistory.forEach((item, index) => {
                html += `
                    <div class="history-item">
                        <strong>${item.gstin}</strong> - ${item.timestamp}
                        <button onclick="loadFromHistory(${index})" style="margin-left: 10px; font-size: 12px;">Load</button>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function loadFromHistory(index) {
            const item = searchHistory[index];
            if (item) {
                document.getElementById('gstinInput').value = item.gstin;

                document.getElementById('result').innerHTML = `
                    <div class="success">
                        <h3>Previously Retrieved GST Details for ${item.gstin}:</h3>
                        <pre>${JSON.stringify(item.details, null, 2)}</pre>
                    </div>
                `;
            }
        }

        function clearForm() {
            document.getElementById('captchaInput').value = '';
            document.getElementById('gstinInput').value = '';
            document.getElementById('result').innerHTML = '';
        }

        // Initialize with a CAPTCHA on page load
        window.onload = function() {
            getNewCaptcha();
        };
    </script>
</body>
</html>
