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
    '$2y$12$wsyBV6sDzU/Le1bQZwkHxesAWPhP/lPO.dnWVAKRi27EtnNli.smS',
    NOW(),
    NOW()
FROM roles r
WHERE r.name = 'admin';
