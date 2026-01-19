@echo off
cd /d C:\xampp\htdocs\gallaassets
php -d max_execution_time=0 artisan queue:work --queue=asset_sync --sleep=1 --timeout=120
