# College-Attendance-System
# 🎓 College Attendance Management System (CAMS)

A role-based web application designed to manage and automate attendance processes in colleges. The system provides separate dashboards for Director, HOD, Teachers, and Students, enabling efficient attendance management, monitoring, and reporting.

## 📌 Project Overview

The College Attendance Management System (CAMS) is developed to simplify attendance tracking and academic administration within educational institutions. The system offers secure authentication, attendance recording, report generation, and user management through an intuitive web interface.

## 🚀 Features

### Director Panel

* Manage Departments
* Manage Courses
* Manage Teachers
* Manage Students
* View Attendance Reports
* Monitor College Activities

### HOD Panel

* Manage Department Teachers
* Monitor Teacher Attendance
* View Student Attendance
* Generate Department Reports

### Teacher Panel

* Mark Student Attendance
* View Attendance Records
* Generate Attendance Reports
* Manage Assigned Classes

### Student Panel

* View Personal Attendance
* View Attendance Percentage
* Access Academic Information

## 🔐 Authentication System

The system supports secure role-based login for:

* Director
* HOD
* Teacher
* Student

Each user is redirected to their respective dashboard after successful login.

## 🛠️ Technology Stack

### Frontend

* HTML5
* CSS3
* Bootstrap
* JavaScript

### Backend

* PHP

### Database

* MySQL

### Development Environment

* XAMPP

## 📂 Project Structure

```text
CAMS/
│
├── Admin/
├── HOD/
├── ClassTeacher/
├── Student/
├── Includes/
├── uploads/
├── img/
├── vendor/
├── index.php
└── database.sql
```

## 💻 Installation Guide

### 1. Clone Repository

```bash
git clone https://github.com/your-username/CAMS.git
```

### 2. Move Project

Copy the project folder into:

```text
xampp/htdocs/
```

### 3. Create Database

Open phpMyAdmin and create:

```sql
cams_db
```

### 4. Import Database

Import the provided SQL file into MySQL.

### 5. Configure Database

Update database credentials inside:

```php
Includes/dbcon.php
```

### 6. Start Server

Start:

* Apache
* MySQL

from XAMPP Control Panel.

### 7. Run Application

Open:

```text
http://localhost/CAMS
```

## 📊 Key Modules

* Attendance Management
* Student Management
* Teacher Management
* Department Management
* Course Management
* Report Generation
* Role-Based Access Control

## 📱 Progressive Web App (PWA)

The system includes:

* Web Manifest
* Service Worker
* Offline Asset Caching
* Mobile-Friendly Interface

## 🎯 Future Enhancements

* Mobile Application
* Biometric Attendance
* RFID Integration
* SMS Notifications
* Email Alerts
* Cloud Deployment
* Multi-Language Support

## 📸 Screenshots

### Login Page

Add screenshot here

### Director Dashboard

Add screenshot here

### HOD Dashboard

Add screenshot here

### Teacher Dashboard

Add screenshot here

### Student Dashboard

Add screenshot here

## 👨‍💻 Author

**Shivam Kumar**

Bachelor of Computer Applications (BCA)

Punjab Institute of Management & Technology (PIMT)

## 📄 License

This project is developed for educational and learning purposes.
