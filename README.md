# WordPress + MariaDB + PHP + Nginx (with SSL) using Podman Compose

This setup runs a local WordPress development environment using Podman Compose, with MariaDB, PHP CLI, and Nginx (supporting HTTPS with self-signed certificates).

## Prerequisites
- [Podman](https://podman.io/) and [podman-compose](https://github.com/containers/podman-compose) installed
- [OpenSSL](https://www.openssl.org/) installed (for generating SSL certificates)

## About SSL Certificates and OpenSSL

To enable HTTPS locally, you need SSL certificates. In this setup, we use **OpenSSL** to generate a self-signed certificate. This means:
- Your browser will warn that the certificate is not trusted (because it's not signed by a public Certificate Authority), but it is secure for local development.
- The certificate files are used by Nginx to encrypt traffic on `https://localhost:8443`.

**Certificate files:**
- `localhost.pem`: The public certificate.
- `localhost-key.pem`: The private key.

These files are mounted into the Nginx container at `/etc/nginx/certs/`.

## Folder Structure
```
docker-compose.yml
nginx/
  nginx.conf
  certs/
    localhost-key.pem
    localhost.pem
```

## Step-by-Step Setup

### 1. Generate SSL Certificates with OpenSSL
Run the following commands in your project directory to create the necessary certificate files:
```sh
mkdir -p nginx/certs
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx/certs/localhost-key.pem \
  -out nginx/certs/localhost.pem \
  -subj "/CN=localhost"
```
- `-x509`: Creates a self-signed certificate.
- `-nodes`: No passphrase (so Nginx can use the key without manual intervention).
- `-days 365`: Valid for 1 year.
- `-newkey rsa:2048`: Generates a new 2048-bit RSA key.
- `-keyout`: Where to save the private key.
- `-out`: Where to save the certificate.
- `-subj "/CN=localhost"`: Sets the certificate's Common Name to `localhost` (required for local HTTPS).

### 2. Review `docker-compose.yml`
- **db**: Runs MariaDB with a persistent volume and environment variables for WordPress.
- **wordpress**: Runs WordPress (PHP-FPM) and mounts the WordPress files.
- **php**: Provides a PHP CLI container for running PHP commands in the WordPress directory.
- **nginx**: Serves the site, proxies PHP requests to the WordPress container, and serves HTTPS using the generated certificates.

### 3. Review `nginx/nginx.conf`
- Configures Nginx to serve WordPress, handle PHP via FastCGI, and (optionally) serve HTTPS (see below for SSL config).

### 4. Start the Services
Run the following command in your project directory:
```sh
podman compose up -d
```

### 5. Access Your Site
- HTTP: [http://localhost:8080](http://localhost:8080)
- HTTPS: [https://localhost:8443](https://localhost:8443) (accept the self-signed certificate warning)

### 6. (Optional) Update Nginx for SSL
Add this to your `nginx.conf` for HTTPS support:
```
server {
    listen 443 ssl;
    server_name localhost;
    root /var/www/html;
    index index.php index.html;
    ssl_certificate /etc/nginx/certs/localhost.pem;
    ssl_certificate_key /etc/nginx/certs/localhost-key.pem;
    # ...rest of config (locations, etc)...
}
```

---

## Stopping and Cleaning Up
To stop the services:
```sh
podman compose down
```

To remove all data (including databases and WordPress files):
```sh
podman volume rm wordpress-podman_db_data wordpress-podman_wordpress_data
```

---

## Troubleshooting

### 1. Data Persists After Rebuild
If you run `podman compose up` and see old data, it's because your database and WordPress data are stored in named volumes (`db_data`, `wordpress_data`).

**To reset your environment:**
1. Stop all containers:
   ```sh
   podman compose down
   ```
2. Remove the persistent volumes (replace names if your project folder is different):
   ```sh
   podman volume rm wordpress-podman_db_data wordpress-podman_wordpress_data
   ```
3. (Optional) Clean your `src` directory for a fresh WordPress install.
4. Start again:
   ```sh
   podman compose up -d
   ```

---

### 2. Cannot Remove Volume: "volume is being used"
If you see an error like:
```
Error: volume wordpress-podman_db_data is being used by the following container(s): ...
```
It means a container is still using the volume. To fix:
1. Stop all containers:
   ```sh
   podman compose down
   ```
2. If the error persists, list all containers:
   ```sh
   podman ps -a
   ```
3. Remove the container using the volume:
   ```sh
   podman rm <container_id>
   ```
4. Remove the volume:
   ```sh
   podman volume rm wordpress-podman_db_data wordpress-podman_wordpress_data
   ```

---

### 3. Custom PHP Development with WordPress
- If you want to develop custom PHP files (like `phpinfo.php`) alongside WordPress, use a bind mount for `/var/www/html` (e.g., `./src:/var/www/html`).
- Place your custom PHP files in the `src` directory. They will be available at `http://localhost:8080/yourfile.php`.
- WordPress files should also be in `src`.

---

### 4. 400 Bad Request: The plain HTTP request was sent to HTTPS port
- This means you tried to access `https://localhost:8443` with HTTP, or vice versa.
- Always use `https://` for port 8443 and `http://` for port 8080.
- If you want HTTP to redirect to HTTPS, add a redirect in your Nginx config.

---

### 5. Browser SSL Warnings (Self-Signed Cert)
- Browsers will warn about self-signed certificates. This is normal for local development.
- Click "Advanced" and "Accept the Risk and Continue" in Firefox (or similar in other browsers).
- For production, use a certificate from a trusted CA.

---

### 6. Using php-cli in Docker/Podman
- To run PHP commands in the `php-cli` container:
  ```sh
  podman compose exec php-cli php -a
  podman compose exec php-cli php yourscript.php
  ```
- The working directory is `/var/www/html` (your `src` folder).

---

### 7. Resetting Everything
To completely reset your environment (including database and files):
1. Stop all containers:
   ```sh
   podman compose down
   ```
2. Remove all volumes:
   ```sh
   podman volume rm wordpress-podman_db_data wordpress-podman_wordpress_data
   ```
3. (Optional) Delete or clean your `src` directory.
4. Start again:
   ```sh
   podman compose up -d
   ```

---

### 8. Migrating Your WordPress Site (Database & Files)

If you want to migrate your WordPress site (move it to another environment, or back it up/restore):

#### Exporting (Backup)
1. **Database:**
   - Run this command to export the database from the running db container:
     ```sh
     podman compose exec db mysqldump -u wordpress -pwordpress wordpress > db_backup.sql
     ```
2. **Files:**
   - Your WordPress files are in the `src` directory. You can back them up by copying or archiving this folder:
     ```sh
     tar czvf src_backup.tar.gz src/
     ```

#### Importing (Restore)
1. **Database:**
   - Copy your backup SQL file into the container (if needed):
     ```sh
     podman cp db_backup.sql $(podman compose ps -q db):/db_backup.sql
     ```
   - Import it into the database:
     ```sh
     podman compose exec db mysql -u wordpress -pwordpress wordpress < db_backup.sql
     ```
2. **Files:**
   - Extract your backup into the `src` directory:
     ```sh
     tar xzvf src_backup.tar.gz -C ./
     ```

**Note:**
- Always stop your containers before restoring files or database to avoid conflicts:
  ```sh
  podman compose down
  ```
- After restoring, start your containers again:
  ```sh
  podman compose up -d
  ```

---

## Database Management with Adminer

You can use [Adminer](https://www.adminer.org/) for easy database management via your browser.

- Access Adminer at: [http://localhost:8081](http://localhost:8081)
- System: MySQL
- Server: db
- Username: wordpress
- Password: wordpress
- Database: wordpress

---

## Useful SQL Queries for WordPress

You can run these queries in Adminer or from the command line:
```sh
podman compose exec db mysql -u wordpress -pwordpress wordpress
```

### Show All Tables
```sql
SHOW TABLES;
```

### List All Users
```sql
SELECT ID, user_login, user_email FROM wp_users;
```

### Change a User Password (replace `newpassword` and `1` with your values)
```sql
UPDATE wp_users SET user_pass = MD5('newpassword') WHERE ID = 1;
```

### Promote a User to Administrator (replace `1` with your user ID)
```sql
UPDATE wp_usermeta SET meta_value = 'a:1:{s:13:"administrator";b:1;}' WHERE user_id = 1 AND meta_key = 'wp_capabilities';
```

### List All Posts
```sql
SELECT ID, post_title, post_status FROM wp_posts WHERE post_type = 'post';
```

### Search for a String in All Posts
```sql
SELECT ID, post_title FROM wp_posts WHERE post_content LIKE '%searchterm%';
```

### Delete All Comments
```sql
DELETE FROM wp_comments;
```

---

### Complex SQL Queries for WordPress

You can use these advanced queries for deeper troubleshooting and management:

#### 1. Find All Posts with a Specific Meta Key and Value
```sql
SELECT p.ID, p.post_title, pm.meta_key, pm.meta_value
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = 'your_meta_key' AND pm.meta_value = 'your_value';
```

#### 2. List All Plugins and Their Status
```sql
SELECT option_value AS active_plugins
FROM wp_options
WHERE option_name = 'active_plugins';
```

#### 3. Count Posts by Status
```sql
SELECT post_status, COUNT(*) AS count
FROM wp_posts
WHERE post_type = 'post'
GROUP BY post_status;
```

#### 4. Find All Users with a Specific Role
```sql
SELECT u.ID, u.user_login, u.user_email
FROM wp_users u
JOIN wp_usermeta um ON u.ID = um.user_id
WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%administrator%';
```

#### 5. Find All Comments Pending Moderation
```sql
SELECT comment_ID, comment_author, comment_content
FROM wp_comments
WHERE comment_approved = '0';
```

#### 6. Bulk Update Post Author
Change all posts from author ID 2 to author ID 1:
```sql
UPDATE wp_posts SET post_author = 1 WHERE post_author = 2;
```

#### 7. Find Orphaned Post Meta (meta with no post)
```sql
SELECT * FROM wp_postmeta pm
LEFT JOIN wp_posts p ON pm.post_id = p.ID
WHERE p.ID IS NULL;
```

#### 8. Delete All Revisions (to clean up the database)
```sql
DELETE FROM wp_posts WHERE post_type = 'revision';
```

---

## Database Exploration Queries

Use these queries to explore and inspect your WordPress database structure and contents. Run them in Adminer or from the command line:

### Show All Databases
```sql
SHOW DATABASES;
```

### Show All Tables in the Current Database
```sql
SHOW TABLES;
```

### Show Table Structure (Columns, Types, Keys)
Replace `wp_users` with any table name:
```sql
DESCRIBE wp_users;
```
Or:
```sql
SHOW COLUMNS FROM wp_users;
```

### Show Indexes for a Table
```sql
SHOW INDEX FROM wp_posts;
```

### Show All Table Status (Size, Engine, etc.)
```sql
SHOW TABLE STATUS;
```

### List All Foreign Keys in the Database
```sql
SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;
```

### Get Row Counts for All Tables
```sql
SELECT table_name, table_rows
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE();
```

### Get Database Size (in MB)
```sql
SELECT table_schema AS 'Database',
  ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = DATABASE()
GROUP BY table_schema;
```

---

## Customizing PHP Settings (`php.ini`)

For local development, you can override PHP settings by providing your own `php.ini` file. This is useful for increasing upload limits, enabling error reporting, or changing other PHP behaviors.

- A recommended `php.ini` is provided at `nginx/php.ini` in this project.
- This file is mounted into both the `wordpress` and `php` containers at:
  `/usr/local/etc/php/conf.d/custom.ini`
- This is a standard override path for PHP settings in official Docker images. Any `.ini` file in `/usr/local/etc/php/conf.d/` will be loaded automatically by PHP.

**Example settings in `nginx/php.ini`:**
```ini
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
date.timezone = UTC
```

You can edit `nginx/php.ini` to suit your needs. After making changes, restart your containers:
```sh
podman compose down
podman compose up -d
```

---

