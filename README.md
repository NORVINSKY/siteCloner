# siteCloner
Клонер сайтов сделанный на основе клиента от SeoDor

HTACCESS

Options -Indexes
RewriteEngine on
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [L]
