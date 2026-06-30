# Whusup

```{=html}
<p align="center">
```
`<img src="docs/images/whusup-banner.png" alt="Whusup Banner" width="900">`{=html}
```{=html}
</p>
```
```{=html}
<p align="center">
```
`<strong>`{=html}Real People. No Ads.`</strong>`{=html}`<br>`{=html} A
modern social networking platform engineered with PHP, MariaDB,
Bootstrap, and AWS.
```{=html}
</p>
```
```{=html}
<p align="center">
```
`<img src="https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white">`{=html}
`<img src="https://img.shields.io/badge/MariaDB-11+-003545?style=for-the-badge&logo=mariadb&logoColor=white">`{=html}
`<img src="https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white">`{=html}
`<img src="https://img.shields.io/badge/AWS-EC2%20%7C%20S3%20%7C%20CloudFront%20%7C%20SES%20%7C%20Rekognition-FF9900?style=for-the-badge&logo=amazonaws&logoColor=white">`{=html}
```{=html}
</p>
```

------------------------------------------------------------------------

## Overview

Whusup is a cloud-hosted social networking application built as a
full-stack software engineering project. The platform combines a
responsive Bootstrap interface, asynchronous user interactions, cloud
media delivery, and secure backend services into a cohesive social
experience.

## Key Features

### Accounts

-   Secure registration and login
-   Email verification
-   Profile photos
-   Password hashing
-   Session authentication

### Social

-   Create, edit, and delete posts
-   Image uploads
-   Nested comments
-   Post and comment likes
-   Follow / unfollow
-   Personalized and public feeds
-   Relative timestamps
-   AJAX-powered interactions

### Cloud

-   Amazon EC2
-   Amazon S3
-   Amazon CloudFront
-   Amazon SES
-   Amazon Rekognition

------------------------------------------------------------------------

## Screenshots

Create a folder:

``` text
docs/
└── images/
```

Recommended files:

  Image           Filename
  --------------- -------------------
  Hero banner     whusup-banner.png
  Landing page    landing-page.png
  Dashboard       dashboard.png
  Notifications   notifications.png
  Profile         profile.png
  Mobile          mobile.png

Example:

``` markdown
![Landing](docs/images/landing-page.png)

![Dashboard](docs/images/dashboard.png)
```

------------------------------------------------------------------------

## Architecture

``` text
Browser
   │
Bootstrap UI
   │
Apache (EC2)
   │
PHP Application
 ├── MariaDB
 ├── Amazon S3
 ├── CloudFront
 ├── Amazon SES
 └── Amazon Rekognition
```

------------------------------------------------------------------------

## Technology

  Layer      Technology
  ---------- --------------------------------------------
  Backend    PHP, PDO
  Database   MariaDB
  Frontend   Bootstrap 5, HTML5, CSS3, JavaScript, AJAX
  Cloud      EC2, S3, CloudFront, SES, Rekognition

------------------------------------------------------------------------

## Security

-   Prepared SQL statements
-   Password hashing
-   Email verification
-   Session authentication
-   Input validation
-   Spam protection
-   Sensitive configuration excluded from Git

------------------------------------------------------------------------

## Roadmap

### Completed

-   Authentication
-   Notifications
-   Image uploads
-   AWS integration
-   Follow system
-   Responsive design

### Planned

-   Video uploads
-   Direct messaging
-   Search
-   Hashtags
-   Mentions
-   Trending
-   Dark mode
-   Progressive Web App

------------------------------------------------------------------------

## Getting Started

``` bash
git clone https://github.com/ccagle2/whusup.git
cd whusup
composer install
```

Then configure:

1.  `.env`
2.  AWS credentials
3.  Database
4.  Apache
5.  SQL schema

------------------------------------------------------------------------

## Contributing

Issues and pull requests are welcome.

------------------------------------------------------------------------

## Author

**Christopher Cagle**

GitHub: https://github.com/ccagle2

------------------------------------------------------------------------

## License

Educational, demonstration, and portfolio use.
