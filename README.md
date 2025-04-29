# County Government Document Tracking System

A comprehensive system for tracking documents through various government departments with different user roles and approval workflows.

## System Overview

This document tracking system manages the flow of documents from contractors through various government departments, ensuring proper review, approval, and payment processing. The system includes QR code generation for document tracking, notifications at each stage, and comprehensive reporting.

## User Roles and Responsibilities

### 1. Clerk

The clerk serves as the primary entry point and final checkpoint in the document workflow.

**Responsibilities:**
- Create contractor accounts
- Upload documents into the system
- Assign QR codes to documents for tracking
- Define document trails (predefined paths documents will follow)
- Move documents to specific departments
- Generate reports of document movements
- Receive completed documents after senior manager approval
- Mark documents as completed

**Access Rights:**
- Full access to contractor management
- Document upload capabilities
- Document movement between departments
- Report generation
- Document trail definition
- Final document processing

### 2. Contractor

Contractors are external users who submit documents to the government for processing.

**Responsibilities:**
- View their own documents in the system
- Track document movements through departments
- Receive notifications about document status changes

**Access Rights:**
- View only their own documents
- Track document movement history
- Access to document status updates via notifications
- Cannot modify or move documents

### 3. Assistant Manager

Assistant managers are the first level of management approval within a department.

**Responsibilities:**
- Review documents assigned to their department
- Approve documents before forwarding to senior managers
- Send documents to senior managers in the same department

**Access Rights:**
- View documents in their department
- Approve documents
- Move documents to senior managers
- Cannot send documents to other departments

### 4. Senior Manager

Senior managers are the final decision-makers within each department.

**Responsibilities:**
- Review documents approved by assistant managers
- Make final approval decisions (approve/reject)
- Process payments for approved documents
- Mark documents as complete and send back to clerk
- Send rejection notices with reasons

**Access Rights:**
- View documents in their department
- Approve or reject documents
- Process payments
- Mark documents as complete
- Send documents back to clerks

## Document Workflow

### 1. Document Creation and Entry
- Clerk creates a contractor account (if one doesn't exist)
- Clerk uploads a document to the system
- System generates a QR code with contractor ID and document information
- Clerk assigns the document to a specific trail or selects the first department

### 2. Departmental Processing
- Document moves through departments based on the predefined trail
- Contractors can view the document's movement through the system
- In each department, the document follows a strict approval hierarchy:
  1. First reviewed by Assistant Manager
  2. Then reviewed by Senior Manager after assistant manager approval

### 3. Assistant Manager Review
- Assistant manager reviews the document
- If approved, the document is forwarded to the senior manager in the same department
- Notification is sent to the senior manager
- Status is updated to "assistant_approved"

### 4. Senior Manager Processing
- Senior manager has four possible actions:
  1. **Approve**: Mark the document as approved
  2. **Reject**: Return the document with rejection reasons
  3. **Pay**: Process payment for the document
  4. **Complete**: Mark as complete and send to clerk for final processing

### 5. Document Completion
- When the senior manager marks a document as complete, it's sent back to the clerk
- The clerk processes the final document and marks it as "done"
- Contractor is notified that the document has been processed
- All document movements are recorded for tracking and reporting

## Document Trails

- Clerks can define predefined paths (trails) for document movement
- Each trail specifies the sequence of departments a document must go through
- Documents automatically follow the trail unless overridden by an administrator
- Each department in the trail must follow the assistant manager → senior manager approval process

## Notifications System

- Contractors receive notifications at each stage of the document process
- Managers receive notifications when documents need their review
- Clerks receive notifications when documents are complete and ready for final processing
- All notifications are tracked and remain accessible for future reference

## Reporting Capabilities

- Clerks can generate reports about document movements
- Reports can be filtered by contractor, department, date range, and status
- Movement history is preserved for audit purposes
- Document status updates are time-stamped for accurate tracking

## Security and Access Control

- Each user can only access information relevant to their role
- Contractors can only view their own documents
- Department staff can only see documents assigned to their department
- Only clerks and administrators can create and manage user accounts
- Secure QR codes provide quick access to document information

---

This system ensures transparent tracking of documents through government departments while maintaining a strict approval hierarchy and providing clear visibility to contractors about their document status. 