# Whusup

<p align="center">
  <img src="docs/images/whusup-banner.png" alt="Whusup Banner" width="900">
</p>

<p align="center">
<strong>Real People. No Ads.</strong><br>
A modern social networking platform built with PHP, MariaDB, Bootstrap, and AWS.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MariaDB-11+-003545?style=for-the-badge&logo=mariadb&logoColor=white" alt="MariaDB">
  <img src="https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap">
  <img src="https://img.shields.io/badge/AWS-EC2%20%7C%20S3%20%7C%20CloudFront%20%7C%20SES%20%7C%20Rekognition-FF9900?style=for-the-badge&logo=amazonaws&logoColor=white" alt="AWS">
</p>

---

## Overview

Whusup is a full-stack social networking application built with PHP and AWS cloud services. It focuses on responsive design, privacy, secure authentication, and modern social networking features.

## Features

### User Accounts

- Secure registration and login
- Email verification
- Profile pictures
- Password hashing
- Secure sessions

### Social Features

- Create, edit, and delete posts
- Image uploads
- Nested comments and replies
- Likes for posts and comments
- Follow / unfollow users
- Public and personalized feeds
- AJAX-powered interactions
- Responsive mobile interface

### AWS Integration

- Amazon EC2
- Amazon S3
- Amazon CloudFront
- Amazon SES
- Amazon Rekognition

---

## Architecture

```text
Users
   │
   ▼
CloudFront
   │
Apache (EC2)
   │
PHP Application
 ├── MariaDB
 ├── Amazon S3
 ├── Amazon SES
 └── Amazon Rekognition
```

---

## Technology Stack

| Layer | Technology |
|------|------------|
| Backend | PHP, PDO |
| Database | MariaDB |
| Frontend | Bootstrap 5, HTML5, CSS3, JavaScript, AJAX |
| Cloud | EC2, S3, CloudFront, SES, Rekognition |

---

## Security

- Prepared SQL statements
- Password hashing
- Email verification
- Input validation
- Spam mitigation
- Sensitive credentials excluded from Git

---

## Roadmap

### Completed

- Authentication
- Image uploads
- Notifications
- Follow system
- CloudFront integration

### Planned

- Video uploads
- Direct messaging
- Hashtags
- User mentions
- Trending content
- Dark mode
- Progressive Web App

---

## Getting Started

```bash
git clone https://github.com/ccagle2/whusup.git
cd whusup
composer install
```

Then configure your environment, database, AWS credentials, and import the SQL schema.

---

## Author

**Christopher Cagle**

GitHub: https://github.com/ccagle2

---

## License

This repository is provided for educational, demonstration, and portfolio purposes.
