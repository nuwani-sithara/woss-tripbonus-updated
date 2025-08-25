# Job Approval Workflow - Updated System

## Overview
This system has been updated to include a new approval stage with a "Supervisor-in-Charge" role that sits between the Supervisor (job creator) and the Operations Manager.

## New Approval Workflow

### 1. Job Creation
- **Supervisor** creates a job
- Job status: `pending_supervisor_in_charge_approval`

### 2. Supervisor-in-Charge Approval
- **Supervisor-in-Charge** reviews the job
- Can: Approve, Reject, or Request Clarification
- If approved: Job moves to Operations Manager
- If rejected: Job goes back to Supervisor
- If clarification requested: Job stays with Supervisor-in-Charge until resolved

### 3. Operations Manager Approval
- **Operations Manager** reviews jobs approved by Supervisor-in-Charge
- Can: Approve, Reject, or Request Clarification
- If approved: Job is fully approved
- If rejected: Job goes back to Supervisor-in-Charge for review

### 4. Supervisor Review (for rejected jobs)
- **Supervisor** can modify or resubmit rejected jobs
- Modified jobs go back to Supervisor-in-Charge for approval

## Clarification Workflow

### Clarification Request Flow:
1. **Supervisor-in-Charge requests clarification** → **Supervisor (job creator) resolves**
2. **Operations Manager requests clarification** → **Supervisor-in-Charge resolves**

### Clarification Resolution Process:
- When a clarification is requested, the system automatically sets the `clarification_resolverID` based on who should resolve it
- The resolver receives the clarification in their interface
- Once resolved, the clarification status changes to `1` (resolved)
- **For Supervisor-in-Charge clarifications**: The resolved clarification goes back to Supervisor-in-Charge for approval
- **Only after clarification is approved by Supervisor-in-Charge** can the job proceed through the approval workflow

### Clarification Status Values:
- **`0`** - Open/Pending Resolution
- **`1`** - Resolved (waiting for approval)
- **`2`** - Approved (can proceed)
- **`3`** - Rejected (needs re-resolution)

## New Approval Stages

The system now uses these approval stages in the `approvals` table:

1. **`supervisor_in_charge_approval`** - New stage for Supervisor-in-Charge review
2. **`job_approval`** - Operations Manager approval (existing)
3. **`supervisor_review`** - When Operations Manager rejects, goes back to Supervisor

## Approval Status Values

- **`0`** - Pending
- **`1`** - Approved
- **`2`** - Clarification Requested
- **`3`** - Rejected

## Clarification Status Values

- **`0`** - Open/Pending Resolution
- **`1`** - Resolved
- **`2`** - Pending Approval (for resolved clarifications)

## New Role

- **Role ID**: `13` (updated from 5)
- **Role Name**: `supervisor-in-charge`
- **Permissions**: Can approve/reject jobs before they go to Operations Manager

## Files Created/Modified

### New Files:
1. **`controllers/supervisorInChargeController.php`** - Handles Supervisor-in-Charge logic
2. **`views/supervisorInChargeApproval.php`** - Approval interface for Supervisor-in-Charge
3. **`views/supervisorInChargeDashboard.php`** - Dashboard for Supervisor-in-Charge
4. **`views/components/supervisorInChargeSidebar.php`** - Navigation sidebar

### Modified Files:
1. **`controllers/approveJobsController.php`** - Updated to only show jobs approved by Supervisor-in-Charge
2. **`APPROVAL_WORKFLOW_README.md`** - Updated documentation

## Database Changes

### Approvals Table
The `approvals` table now stores multiple approval records per job:

```sql
-- Example approval records for a single job:
INSERT INTO approvals (jobID, approval_stage, approval_status, approval_by, approval_date) VALUES
(123, 'supervisor_in_charge_approval', 1, 13, '2024-01-01 10:00:00'),  -- Approved by Supervisor-in-Charge
(123, 'job_approval', 0, NULL, NULL);                                  -- Pending Operations Manager approval
```

### Clarifications Table
The `clarifications` table now properly tracks who should resolve each clarification:

```sql
-- Example clarification records:
INSERT INTO clarifications (
    jobID, 
    approvalID, 
    clarification_requesterID, 
    clarification_request_comment, 
    clarification_resolverID,
    clarification_status
) VALUES
(123, 456, 13, 'Need more details about vessel specifications', 5, 0),  -- SIC requests, Supervisor resolves
(123, 789, 4, 'Please clarify job timeline', 13, 0);                   -- OM requests, SIC resolves
```

## Job Flow Examples

#### Successful Approval Path:
1. Job created by Supervisor (jobID: 123)
2. Supervisor-in-Charge approves → `approval_stage: 'supervisor_in_charge_approval', approval_status: 1`
3. System creates new approval record → `approval_stage: 'job_approval', approval_status: 0`
4. Operations Manager sees job in their approval list
5. Operations Manager approves → `approval_stage: 'job_approval', approval_status: 1`
6. Job is fully approved

#### Rejection Path:
1. Operations Manager rejects job → `approval_stage: 'job_approval', approval_status: 3`
2. Supervisor-in-Charge sees rejected job in their "rejected by OM" section
3. Supervisor-in-Charge can modify or resubmit
4. If resubmitted, new approval record created → `approval_stage: 'supervisor_in_charge_approval', approval_status: 0`

#### Clarification Path:
1. Supervisor-in-Charge requests clarification → `clarification_resolverID` set to Supervisor
2. Supervisor resolves clarification → `clarification_status: 1`
3. **Supervisor-in-Charge reviews and approves the resolution** → `clarification_status: 2`
4. Job continues through approval workflow

## How to Use

### For Supervisor-in-Charge:
1. Access `/views/supervisorInChargeApproval.php`
2. Review pending jobs
3. Approve, reject, or request clarification
4. Handle jobs rejected by Operations Manager
5. Resolve clarifications requested by Operations Manager

### For Operations Manager:
1. Access `/views/approvejobs.php` (existing)
2. Only see jobs that have been approved by Supervisor-in-Charge
3. Approve, reject, or request clarification
4. Clarifications are automatically sent to Supervisor-in-Charge for resolution

### For Supervisor (Job Creator):
1. When job is rejected, they can modify and resubmit
2. Job goes back through the approval workflow
3. Must resolve clarifications requested by Supervisor-in-Charge

## Security

- Each role can only access their designated approval stage
- Role-based access control implemented
- Session validation for all approval actions
- Clarification resolvers are automatically set based on workflow rules

## Testing

To test the new workflow:

1. **Create a Supervisor-in-Charge user** with `roleID = 13`
2. **Create a job** as a Supervisor
3. **Login as Supervisor-in-Charge** and approve the job
4. **Login as Operations Manager** and see the job in their approval list
5. **Test clarification flow** by requesting clarification as Operations Manager
6. **Verify Supervisor-in-Charge** can see and resolve the clarification
7. **Test rejection flow** by rejecting a job as Operations Manager
8. **Verify Supervisor-in-Charge** can see rejected jobs

## Troubleshooting

### Common Issues:

1. **Jobs not appearing in Supervisor-in-Charge list**
   - Check if job has `jobCreatedBy` value
   - Verify no existing approval records for this job

2. **Jobs not appearing in Operations Manager list**
   - Check if Supervisor-in-Charge has approved the job
   - Verify approval record exists with `approval_stage: 'job_approval'`

3. **Clarifications not being assigned correctly**
   - Check `clarification_resolverID` in clarifications table
   - Verify the resolver has the correct role permissions

4. **Approval actions not working**
   - Check user session and role permissions
   - Verify database connection and table structure

### Database Queries for Debugging:

```sql
-- Check all approvals for a specific job
SELECT * FROM approvals WHERE jobID = [JOB_ID] ORDER BY approval_stage, approval_date;

-- Check pending approvals for Supervisor-in-Charge
SELECT j.jobID, j.comment, a.approval_status, a.approval_stage 
FROM jobs j 
LEFT JOIN approvals a ON j.jobID = a.jobID AND a.approval_stage = 'supervisor_in_charge_approval'
WHERE j.jobCreatedBy IS NOT NULL 
AND (a.approvalID IS NULL OR a.approval_status = 0);

-- Check jobs ready for Operations Manager
SELECT j.jobID, j.comment, a.approval_status, a.approval_stage 
FROM jobs j 
JOIN approvals a ON j.jobID = a.jobID 
WHERE a.approval_stage = 'job_approval' 
AND a.approval_status = 0;

-- Check clarifications and their resolvers
SELECT c.*, j.jobID, u.fname, u.lname as resolver_name
FROM clarifications c
JOIN jobs j ON c.jobID = j.jobID
JOIN users u ON c.clarification_resolverID = u.userID
WHERE c.clarification_status = 0;
```

## Future Enhancements

1. **Email notifications** for approval status changes and clarification requests
2. **Approval history tracking** with timestamps and comments
3. **Bulk approval** functionality
4. **Approval delegation** when users are unavailable
5. **Mobile-responsive** approval interface
6. **Clarification templates** for common request types
7. **Auto-escalation** for unresolved clarifications
