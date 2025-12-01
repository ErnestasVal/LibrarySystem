@echo off
echo Setting up project...
copy .env.example .env
composer install
echo Please edit .env file with your database credentials
echo Run: php -S localhost:8000
pause