<img src="https://avideo.tube/website/assets/151/images/avideo_network.png"/>

# AVideo-Encoder-Network (AEN)
Aggregator for AVideo Platform Encoder Servers

AVideo-Encoder-Network (AEN) is a platform designed to streamline your video encoding.

This platform has been implemented so that several encoders can be monitored in real time.

The system works by doing some real-time testing on the encoders servers, with check ping speed, queue encoding length and free memory of each server.

This article asumes you know about <a href="http://git-encoder.avideo.tube/" class="" target="_blank">AVideo Encoder</a>

With this information the (AEN) helps you decide which encoder to use.

The use is the same as using a simple encoder, but this tool will help you choose the best encoder at the time of encoding.

After choosing the encoder you wish to use use the instructions contained here https://github.com/WWBN/AVideo-Encoder/wiki/How-to-submit-videos-to-the-encoder

We have it installed it here with 2 encoders https://network.avideo.com/

# How to install

Open `install/index.php` in your browser. The responsive installer checks PHP 7.4+,
MySQLi, cURL, the SQL schema, and configuration directory permissions.

1. Enter the MySQL/MariaDB host, port, credentials, and database name. **Test connection**
   verifies access without creating a database, tables, or configuration.
2. Confirm the public Encoder Network URL and optionally enter encoder URLs, one per line.
3. Enter the URL and administrator credentials of your AVideo site, then install.

The installer validates the AVideo administrator on the server, creates the database
if needed, imports and verifies the three tables, registers the site and encoders,
and publishes `configuration.php`. An existing empty database can also be used.
The generated configuration uses the selected database port and derives its filesystem
root from `__DIR__`, so moving the application does not leave a stale absolute path.

Errors remain on screen with the failed stage, completed operations, and relevant
Windows/PowerShell or Linux/SQL diagnostic and repair commands. Copyable commands
never include passwords. Replace any account placeholders before running them.
The Windows MySQL client command assumes the XAMPP layout; adjust the executable
path if your MySQL client is installed elsewhere.

Existing populated databases and nonempty configuration files are protected from
reinstallation. SQL import errors stop setup immediately; initial registrations
use an InnoDB transaction. Empty tables from an interrupted schema import can be
reused on retry. If final configuration publication fails after database commit,
the installer preserves the prepared PHP file and shows the command to finish.
Do not repeat installation against that populated database: complete file recovery.

To run integration tests against disposable databases and a temporary document root:

```sh
python tests/installer_integration.py --mysql /path/to/mysql --user root
```

The tests require Python 3, PHP, and a local MySQL account with database creation and
deletion privileges. Use `MYSQL_PWD` if that test account requires a password.
They do not load or alter the application's own configuration or database.

# How to add or remove encoders to the network

The network dashboard shows server availability, reported queue size, active encoding
jobs, concurrent capacity, upload limits, memory usage, and encoder version. Status
checks run through the network server every 30 seconds while the browser tab is
active. Response times include authentication and status processing, not ICMP ping.
The chart keeps up to 20 successful samples from the current visit.

Totals include only confirmed metrics and show reporting coverage. Missing values
appear as a dash. Access errors, invalid responses, and timeouts have distinct
diagnostics; a failed check does not imply that the encoder is offline. Recommendations
compare queue size per concurrent slot, then capacity, response time, and free memory.

The first encoder opens automatically; a registered encoder on the network site's
hostname takes priority. Use **View queue**, **Encoder & queue**, or the workspace
tabs to switch with one click. Each opened encoder keeps its frame and ongoing work
when switching tabs. **Reload encoder** and **Close this encoder** affect only the
active frame. Server metrics and diagnostics are available in a collapsible section.

Use **Open directly** if browser cookie restrictions interfere with embedded sign-in.
Status requests use the current user's session credentials. HTTPS certificate
verification stays enabled for status checks. If both embedded and direct sign-in
stall, check that the encoder can reach the AVideo site's public login endpoint;
successful access from the local network alone does not confirm external reachability.

Dashboard checks use isolated fixtures and never access production data:

```sh
node tests/network_metrics.js
python tests/network_dashboard.py
python tests/network_login.py
```

The browser checks require Python Playwright, Chrome, and PHP. They simulate encoder
responses; actual upload and encoding behavior remains managed by the encoder service.
Login checks use a temporary document root and mock video site to verify failed
responses and POST authentication with both cURL and PHP stream transports. Login
connections verify TLS certificates and have a 15-second server timeout.

We have created a tool to help you add or remove encoders.

This tool is inside the installation directory and must be executed by command line.

1 - Go to your terminal using SSH and navigate to the directory AVideo-Network/install

2 - type `php encoders.php` and you will see the options

* A - Add new Encoder
* Q - Quit/Exit
* 1 * Remove https://encoder.avideo.com/
* 2 * Remove https://encoder2.avideo.com/

Choose the desired option and follow the instructions.
