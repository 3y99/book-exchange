# Peer-to-Peer Book Exchange

A web-based platform for students to trade, buy, and sell textbooks directly.

**Version**: Final (v2)
**Environment**: PHP + MySQL (XAMPP)
**Status**: Buyer/Seller roles separated; Admin dashboard improved; AI Chat feature planned.

## Overview

The Peer-to-Peer Book Exchange project is designed for university students to exchange textbooks conveniently and safely.
It helps reduce costs, promote book reuse, and create a direct connection between buyers and sellers.

## Technology Stack
Frontend-HTML - CSS
Backend - PHP
Database - MySQL (XAMPP)
Server - Apache
Version Control - Git + GitHub

## Main Features

### User Management
1. Register, login, and logout
2. Role-based system: Buyer, Seller, Admin
3. Admin can ban or unban users

### Book Listings
1. Add, edit, and delete listings
2. Upload book cover images
3. Search and filter by title, author, ISBN, or category

### Transactions
1. Mark listings as Reserved or Sold
2. Buyers can contact sellers
3. AI Chat for buyer-seller communication planned for future versions

### Admin Dashboard
1. Manage users, listings, categories, and reports
2. View transaction history and handle complaints
3. Admin login page: http://localhost/book-exchange/admin/login.php

## How to Run
1. Install Environment
Download and install XAMPP
Start Apache and MySQL

2. Clone the Repository
git clone https://github.com/3y99/book-exchange
Move the folder to: C:\xampp\htdocs\
The full path should be: C:\xampp\htdocs\book-exchange

3. Set Up Database
Open phpMyAdmin
Create a new database named book_exchange
Import the SQL file located at database/book_exchange.sql

4. Configure Database Connection
Edit config.php:

<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // Default for XAMPP
$DB_NAME = 'book_exchange';
$DB_CHARSET = 'utf8mb4';
?>

5. Start the Server
Open a browser and visit(users): http://localhost/book-exchange/
For admin login: http://localhost/book-exchange/admin/login.php

## User Registration
1. Each user must register their own account before logging in.
2. After registration, users can log in as buyer or seller depending on their chosen role.
3. Admin accounts can be manually added in the database by setting the role column to admin.

## Future Plans

1. AI chat assistant for buyer-seller communication
2. Email notification system
3. Image compression and cloud storage
4. Pagination and caching optimization
5. Improved report/review system
