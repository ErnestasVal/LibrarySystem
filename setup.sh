#!/bin/bash
echo "Setting up project..."
cp .env.example .env
composer install
echo "Please edit .env file with your database credentials"
echo "Run: php -S localhost:8000"