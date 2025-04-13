# County Government Document Tracking System

A web-based document management and tracking system designed for Kenyan county government offices to track the movement and approval of physical documents such as invoices, delivery notes, project authorizations, and memos.

## Features

### User Management
- Login and registration with secure password hashing
- Session-based authentication
- Role-based access control (Admin, Clerk, Supervisor, Viewer)
- Different permissions based on user roles

### Document Upload and Management
- PDF document upload functionality
- Document metadata: title, type, department, status, uploader
- Unique document ID generation
- Secure file storage

### Document Tracking & Movement
- Track document movement between departments
- Comprehensive movement logs
- Complete audit trail with timestamps

### Approval Workflow
- Document approval/rejection by authorized users
- Comment addition during approval process
- Status updates after approval actions

### Search & Dashboard
- Advanced search and filtering capabilities
- Admin dashboard with statistics:
  - Total documents uploaded
  - Approved documents
  - Pending documents
  - Rejected documents
  - Documents in movement

## Technology Stack
- Core PHP
- MySQL Database
- Tailwind CSS (via CDN)
- Font Awesome Icons
- JavaScript (vanilla)

## Quick Start

### Demo Credentials
- **Admin Account**
  - Email: admin@county.com
  - Password: Qwerty@123
  - Full access to all system features

- **Test User Account**
  - Email: hassan@gmail.com
  - Password: Qwerty@123
  - Limited access based on assigned role

### Creating Test Users
You can register additional test users through the admin dashboard after logging in with the admin credentials.

## Installation

1. Clone the repository to your web server directory:
   ```
   git clone [repository-url] county_gov_tracking_system
   ```

2. Create a MySQL database and import the included SQL schema:
   ```
   mysql -u username -p database_name < database/schema.sql
   ```

3. Configure the database connection in `config/db.php`:
   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'your_database');
   ```

4. Ensure the uploads directory has appropriate write permissions:
   ```
   chmod 755 uploads/
   ```

5. Access the application through your web browser:
   ```
   http://localhost/county_gov_tracking_system/public/login.php
   ```

## Running the Application

1. Make sure your web server (Apache/XAMPP) is running
2. Navigate to the login page: `http://localhost/county_gov_tracking_system/public/login.php`
3. Log in using the admin credentials provided in the Quick Start section
4. From the admin dashboard, you can:
   - View system statistics
   - Manage users
   - Track documents
   - Register new test users with different roles
   - Access all system features

## File Structure
```
/document-tracker/
│
├── config/
│   └── db.php
│
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── auth.php
│
├── uploads/
│
├── public/
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│   ├── upload.php
│   ├── track.php
│   ├── approve.php
│   ├── move.php
│   └── logout.php
│
├── process/
│   ├── login_process.php
│   ├── register_process.php
│   ├── upload_process.php
│   ├── approve_process.php
│   ├── move_process.php
│   └── delete_process.php
```

## Database Structure

### Tables
- **users**: id, name, email, password, role, department, created_at
- **documents**: id, title, file_path, type, department, status, uploaded_by, created_at
- **document_movements**: id, document_id, from_department, to_department, moved_by, note, moved_at
- **approvals**: id, document_id, approved_by, comment, approved_at

## Usage

### User Roles
- **Admin**: Full system access, manage users, view all documents
- **Supervisor**: Approve/reject documents, view departmental documents
- **Clerk**: Upload and move documents
- **Viewer**: View documents without modification rights

### Document Workflow
1. Clerk uploads document
2. Document can be transferred between departments
3. Supervisor/Admin approves or rejects document
4. All movements and approvals are logged

## Security Features
- CSRF protection
- Password hashing
- Session-based authentication
- Input validation and sanitization

## License
[Specify your license here]

## Contact
County Government Document Tracking System Team  
Email: support@countydocs.example.com

## Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request 