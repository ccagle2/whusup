<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Metadata defaults.
 *
 * Individual pages such as post.php can define these variables
 * before including header.php.
 */
$pageTitle = $pageTitle ?? 'Whusup | Vintage Internet';

$pageDescription = $pageDescription
    ?? 'Whusup is a social media and social networking platform where you can share posts and photos, follow friends, discover discussions, and connect with real people.';

$canonicalUrl = $canonicalUrl
    ?? 'https://whusup.com';

$robotsMeta = $robotsMeta
    ?? 'index, follow';

$ogTitle = $ogTitle ?? 'Whusup | Vintage Internet';

$ogDescription = $ogDescription
    ?? 'Whusup is a social media platform where you can share posts and photos, follow friends, and discover conversations.';

$ogImage = $ogImage
    ?? 'https://whusup.com/assets/preview-image.jpg';

$ogImageAlt = $ogImageAlt
    ?? 'Whusup social media platform preview';

$ogUrl = $ogUrl
    ?? 'https://whusup.com';

$ogType = $ogType
    ?? 'website';

$twitterCard = $twitterCard
    ?? 'summary_large_image';

$twitterTitle = $twitterTitle
    ?? 'Whusup | Vintage Internet';

$twitterDescription = $twitterDescription
    ?? 'Whusup is a social media platform for sharing posts and photos, following friends, and discovering conversations.';

$twitterImage = $twitterImage
    ?? 'https://whusup.com/assets/preview-image.jpg';
?>

<!doctype html>
<html lang="en">
  <head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-H9JWDNYD2D"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
    
      gtag('config', 'G-H9JWDNYD2D');
    </script>
    
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description"
      content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">

    <link rel="canonical"
      href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots"
      content="<?= htmlspecialchars($robotsMeta, ENT_QUOTES, 'UTF-8') ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Include jQuery library from CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="/css/style.css">
    <link
    href="https://fonts.googleapis.com/css2?family=Bangers&family=Poppins:wght@400;600;700;900&display=swap"
    rel="stylesheet"
    >
    
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($ogImageAlt, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') ?>">
    
    <meta name="twitter:card" content="<?= htmlspecialchars($twitterCard, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($twitterTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($twitterDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($twitterImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($ogImageAlt, ENT_QUOTES, 'UTF-8') ?>">
    
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link rel="apple-touch-icon" href="/assets/favicon.png">
    
    <link rel="icon" sizes="32x32" href="/assets/favicon-32.png">
    <link rel="icon" sizes="192x192" href="/assets/favicon-192.png">
    <link rel="apple-touch-icon" href="/assets/favicon-180.png">
    
    <script type="application/ld+json">
    {
      "@context":"https://schema.org",
      "@type":"WebSite",
      "name":"Whusup",
      "url":"https://whusup.com",
      "description":"Whusup is a social media and social networking platform where people share posts and photos, follow friends, discover discussions, and connect with others."
      "publisher":{
        "@type":"Organization",
        "name":"Whusup"
      }
    }
    </script>
  </head>
 
<body class="d-flex flex-column min-vh-100">