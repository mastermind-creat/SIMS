-- Insert default super admin user (password: admin123)
INSERT INTO admin_users (username, password, email, full_name, role, status)
VALUES ('superadmin', '$2y$10$8K1p/a0dR1xqM8K1p/a0dR1xqM8K1p/a0dR1xqM8K1p/a0dR1xqM', 'admin@example.com', 'Super Admin', 'super_admin', 'active'); 