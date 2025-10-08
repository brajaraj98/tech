# Use the official PHP 8.2 image with an Apache web server
FROM php:8.2-apache

# Copy all of your project files into the web directory of the server
COPY . /var/www/html/
