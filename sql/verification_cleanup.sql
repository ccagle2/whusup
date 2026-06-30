DELETE ev
FROM email_verifications ev
JOIN users u ON u.id = ev.user_id
WHERE u.email_verified = 1
OR ev.expires_at < NOW();