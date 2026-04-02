System Installation and Setup  

Step 1: Database Configuration
1.	Launch Server Services: Open the XAMPP Control Panel and start both the Apache and MySQL modules. Ensure the status indicators turn green.
2.	Access Database Manager: Navigate to phpMyAdmin via the web browser at http://localhost/phpmyadmin
3.	Create Database:
	Click on "New" in the sidebar.
	Enter the database name exactly as: scholarship_system. (Note: This must match the configuration in config/db.php).
	Click "Create".
4.	Import Schema:
	Select the newly created scholarship_system database.
	Go to the "Import" tab.
	Click "Choose File" and select the provided SQL file (e.g., scholarship_system.sql).
	Click "Import" to execute the SQL commands and reconstruct the database tables.
Step 2: Application Deployment
1.	Locate Web Root: Navigate to the XAMPP installation directory, typically C:\xampp\htdocs\.
2.	Deploy Source Code:
	Create a new folder named scholarship_system.
	Copy all project source files (including index.php, login.php, config/, student/, reviewer/, etc.) into this directory.
3.	Verify Configuration:
	Ensure the database connection file (config/db.php) contains the correct credentials (default XAMPP credentials: User=root, Password=[empty]).
Step 3: System Access
Once the setup is complete, the application can be accessed via a web browser at:
http://localhost/scholarship_system/


User Guide

1. Student
   
The Student is the entry point for applicants to discover and apply for scholarships.

•	Step 1: Registration & Login: New users must create an account by providing their full name, institutional email, phone number, and address. Once registered, log in using your credentials via the Student Login screen.

•	Step 2: Browse Scholarships: Navigate to the Browse Schemes page to view available financial aid opportunities. Use the "View Requirements" link to check eligibility and deadlines.

•	Step 3: Submit Application: Click the Apply Now button for an open scheme. Fill in the application form with your academic (CGPA) and financial (family income) data.

•	Step 4: Upload Documents: Use the document management tool to upload required files (PDF/JPG/PNG). Finalize the process by clicking Submit Application.

•	Step 5: Track Status & Feedback: Access the Student Dashboard to see real-time updates (Pending, Under Review, or Awarded). Check the Review Feedback page to read quantitative scores and qualitative comments from reviewers.

2. Reviewer
   
The Reviewer facilitates the evaluation of assigned applications using a structured rubric.

•	Step 1: Access Assigned Tasks: Log in to the Reviewer Dashboard to view a list of student applications assigned to you for evaluation.

•	Step 2: Verify Documents: Open the Document Preview Modal to inspect a student's supporting files (e.g., transcripts or ID cards) without downloading them. 

•	Step 3: Request Information: If an application is incomplete, use the Request Info feature to ask the student for missing documents, which automatically sets the application status to "Pending Info".

•	Step 4: Score Application: Open the evaluation workspace and enter scores based on the dynamic rubric (Academic, Co-curricular, and Interview). The system will auto-calculate the weighted total score.

•	Step 5: Submit Review: Add final justification comments and submit the review. The application will then move to the Committee's queue.

3. Committee
   
The Committee is for final decision-making and result publication.

•	Step 1: Review Evaluations: Access the Committee Dashboard to view applications that have been fully scored by reviewers.

•	Step 2: Informed Decision-Making: Open specific applications to see the reviewer’s final scores and detailed comments.

•	Step 3: Approve or Reject: Based on the review data, select either the Approve or Reject action for each candidate.

•	Step 4: Publish Results: Once decisions are finalized, use the Result Publication module to officially release the outcomes. This triggers system-wide notifications to the students.

4. Admin
   
The Admin manages the overall system configuration and user roles.

•	Step 1: User Management: Use the Centralized User Management interface to create, edit, or deactivate accounts for all roles (Student, Reviewer, Committee, Admin).

•	Step 2: Manage Scholarships: Create new scholarship programs by defining their titles, descriptions, and open/close dates.

•	Step 3: Assign Reviewers: Use the Reviewer Assignment Engine to link specific reviewers to pending student applications.

•	Step 4: Archive Programs: Once a scholarship cycle is complete, run the Archive workflow to move the program to historical records, ensuring all reviews are finished before closing.

This project is a personal copy of a collaborative project for demonstration purposes.
<img width="962" height="451" alt="image" src="https://github.com/user-attachments/assets/8a6a663e-42cb-4c1a-b8c4-18e4d325d337" />
<img width="964" height="477" alt="image" src="https://github.com/user-attachments/assets/4e7efad6-30e5-43fc-8166-f5c10290b895" />
<img width="971" height="430" alt="image" src="https://github.com/user-attachments/assets/6d6d985c-e10d-44ef-86c0-23de5d2915e0" />
<img width="962" height="445" alt="image" src="https://github.com/user-attachments/assets/4932cd8b-3523-40a3-b01f-1fb72ac8a361" />
<img width="965" height="453" alt="image" src="https://github.com/user-attachments/assets/e9a38ef1-b66c-43f9-adce-cc707cb9a9fc" />
<img width="957" height="437" alt="image" src="https://github.com/user-attachments/assets/894e2ef1-a070-4a8f-8371-4d1d95502cdd" />
<img width="955" height="450" alt="image" src="https://github.com/user-attachments/assets/c941e91b-93bc-4186-8b3d-e4d7ac91c11a" />
<img width="956" height="464" alt="image" src="https://github.com/user-attachments/assets/dabd2cc4-1fd3-46ec-837e-104221acded1" />
<img width="1016" height="480" alt="image" src="https://github.com/user-attachments/assets/cbf247e3-d651-4c56-8a81-1e8f634f127d" />
