# Whusup

```{=html}
<p align="center">
```
`<img src="https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white">`{=html}
`<img src="https://img.shields.io/badge/MariaDB-11+-003545?style=for-the-badge&logo=mariadb&logoColor=white">`{=html}
`<img src="https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white">`{=html}
`<img src="https://img.shields.io/badge/AWS-S3%20%7C%20CloudFront%20%7C%20SES%20%7C%20EC2-FF9900?style=for-the-badge&logo=amazonaws&logoColor=white">`{=html}
```{=html}
</p>
```
A modern social networking platform built from the ground up using
**PHP**, **MariaDB**, **Bootstrap**, and **Amazon Web Services**.

Whusup was created as a full-stack web application to explore modern
social networking features while emphasizing performance, security,
responsive design, and cloud-native architecture.

------------------------------------------------------------------------

# Features

## User Accounts

-   Secure registration and login
-   Email verification
-   Password hashing
-   User profiles
-   Profile picture uploads

## Social Features

-   Create, edit, and delete posts
-   Image uploads
-   Nested comments and replies
-   Like posts
-   Like comments
-   Follow / unfollow users
-   Personalized feed
-   Public homepage
-   Relative timestamps
-   Infinite scrolling

## Modern User Experience

-   Responsive Bootstrap 5 interface
-   AJAX-powered interactions
-   Live notifications
-   Mobile-friendly design
-   Clickable URLs inside posts
-   Expandable images
-   Modern card layout

## Cloud Integration

-   Amazon EC2 hosting
-   Amazon S3 media storage
-   Amazon CloudFront CDN
-   Amazon SES email verification
-   Amazon Rekognition image moderation

------------------------------------------------------------------------

# Technology Stack

## Backend

-   PHP
-   PDO
-   MariaDB
-   Apache

## Frontend

-   Bootstrap 5
-   HTML5
-   CSS3
-   JavaScript
-   AJAX
-   jQuery

## Cloud Services

-   AWS EC2
-   AWS S3
-   AWS CloudFront
-   AWS SES
-   AWS Rekognition

------------------------------------------------------------------------

# Security

Current protections include:

-   Prepared SQL statements
-   Password hashing
-   Secure session authentication
-   Email verification
-   Image moderation
-   Input validation
-   Spam mitigation
-   Environment-based configuration
-   CloudFront private media delivery

------------------------------------------------------------------------

# Project Structure

``` text
assets/
auth/
config/
includes/
public/
sql/
composer.json
README.md
```

------------------------------------------------------------------------

# Future Roadmap

-   Video uploads
-   Direct messaging
-   Search improvements
-   Trending posts
-   User mentions
-   Hashtags
-   Progressive Web App (PWA)
-   Push notifications
-   Dark mode
-   Advanced moderation tools

------------------------------------------------------------------------

# Local Development

``` bash
git clone https://github.com/ccagle2/whusup.git
composer install
```

Configure your environment variables, database, AWS credentials, import
the SQL schema, and start Apache/MariaDB.

------------------------------------------------------------------------

# Author

**Christopher Cagle**

GitHub: https://github.com/ccagle2

------------------------------------------------------------------------

## License

This repository is provided for educational, demonstration, and
portfolio purposes.
