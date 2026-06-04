DELETE FROM users WHERE email = 'admin@lumina.local';

INSERT INTO users (
    role_id,
    full_name,
    email,
    phone,
    password_hash,
    created_at,
    updated_at
)
SELECT
    r.id,
    'Admin LUMINA',
    'admin@lumina.local',
    '0900000000',
    '$2y$12$O2sZV9mAO/DsrDLT4oom2u02jcy3q8T5degpc8DO.lhxrR8v4JSKy',
    NOW(),
    NOW()
FROM roles r
WHERE r.name = 'admin';
