<?php
session_start();
include '../config/dbConnect.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has role_id = 1
if (!isset($_SESSION['userID']) || 
    !isset($_SESSION['roleID']) || 
    $_SESSION['roleID'] != 1) {
    header("Location: ../index.php?error=access_denied");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Standby Attendance - SubseaOps</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="../assets/img/app-logo1.png"
      type="image/x-icon"
    />

    <!-- Fonts and icons -->
    <script src="../assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
      WebFont.load({
        google: { families: ["Inter:300,400,500,600,700"] },
        custom: {
          families: [
            "Font Awesome 5 Solid",
            "Font Awesome 5 Regular",
            "Font Awesome 5 Brands",
            "simple-line-icons",
          ],
          urls: ["../assets/css/fonts.min.css"],
        },
        active: function () {
          sessionStorage.fonts = true;
        },
      });
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/plugins.min.css" />
    <link rel="stylesheet" href="../assets/css/kaiadmin.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@icon/gg@2.0.0/css/gg.css" />

    <style>
      :root {
        --primary: #4361ee;
        --primary-light: #ebf1ff;
        --secondary: #3f37c9;
        --success: #4cc9f0;
        --info: #4895ef;
        --warning: #f8961e;
        --danger: #f72585;
        --light: #f8f9fa;
        --dark: #212529;
      }
      
      body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f7fb;
      }
      
      .card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        margin-bottom: 24px;
      }
      
      .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 20px 25px;
        border-radius: 10px 10px 0 0 !important;
      }
      
      .card-title {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0;
      }
      
      .page-header {
        margin-bottom: 30px;
      }
      
      .page-header h3 {
        font-weight: 700;
        color: var(--dark);
      }
      
      .breadcrumbs {
        background: transparent;
        padding: 0;
      }
      
      .table thead th {
        border-bottom: 1px solid rgba(0,0,0,0.05);
        font-weight: 600;
        color: var(--dark);
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
      }
      
      .table tbody tr {
        transition: all 0.2s;
      }
      
      .table tbody tr:hover {
        background-color: var(--primary-light);
      }
      
      .form-select, .form-control {
        border-radius: 8px;
        padding: 10px 15px;
        border: 1px solid #e0e0e0;
      }
      
      .btn {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.2s;
      }
      
      .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
      }
      
      .btn-primary:hover {
        background-color: var(--secondary);
        border-color: var(--secondary);
      }
      
      .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
      }
      
      .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #545b62;
      }
      
      #selected-divers-list {
        list-style: none;
        padding: 0;
        margin: 15px 0;
      }
      
      #selected-divers-list li {
        background-color: var(--primary-light);
        padding: 10px 15px;
        margin-bottom: 8px;
        border-radius: 6px;
        color: var(--primary);
        font-weight: 500;
        display: flex;
        align-items: center;
      }
      
      #selected-divers-list li:before {
        content: "✓";
        margin-right: 10px;
        color: var(--success);
      }
      
      .section-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
      }
      
      .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
      }
      
      .badge-success {
        background-color: rgba(76, 201, 240, 0.1);
        color: var(--success);
      }
      
      .diver-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
      }
      
      .selection-summary {
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      }
      
      .selection-summary h5 {
        margin-bottom: 15px;
      }
      
      .action-section {
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      }
      
      .standby-assignment-card {
        background-color: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        transition: all 0.2s;
      }
      
      .standby-assignment-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: var(--primary);
      }
      
      .standby-assignment-card.checked-out {
        background-color: #f8f9fa;
        border-color: #dee2e6;
      }
      
      .standby-assignment-card.checked-out .employee-name {
        color: #6c757d;
      }
      
      .employee-name {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 5px;
      }
      
      .employee-details {
        font-size: 14px;
        color: #6c757d;
        margin-bottom: 10px;
      }
      
      .standby-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
      }
      
      .standby-duration {
        font-size: 14px;
        color: var(--primary);
        font-weight: 500;
      }
      
      .standby-count {
        font-size: 14px;
        color: var(--success);
        font-weight: 500;
      }
      
      .status-badge-checkin {
        background-color: rgba(76, 201, 240, 0.1);
        color: var(--success);
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
      }
      
      .status-badge-checkout {
        background-color: rgba(108, 117, 125, 0.1);
        color: #6c757d;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
      }
      
      .checkout-btn {
        background-color: var(--warning);
        border-color: var(--warning);
        color: white;
        font-size: 12px;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.2s;
      }
      
      .checkout-btn:hover {
        background-color: #e68919;
        border-color: #e68919;
        color: white;
      }
      
      .checkout-btn:disabled {
        background-color: #6c757d;
        border-color: #6c757d;
        cursor: not-allowed;
      }
      
      .no-assignments {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
      }
      
      .no-assignments i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
      }

      /* Add this to your existing CSS */
      .checked-in-row {
          background-color: #f8f9fa;
          color: #6c757d;
      }

      .checked-in-row td {
          opacity: 0.7;
      }

      .checked-in-row .diver-checkbox {
          cursor: not-allowed;
      }
    </style>
  </head>
  <body>
    <div class="wrapper">
      <?php include 'components/sidebar.php'; ?>

      <div class="main-panel">
        <div class="main-header">
          <div class="main-header-logo">
            <!-- Logo Header -->
            <div class="logo-header" data-background-color="dark">
              <a href="../index.html" class="logo">
                <img
                  src="../assets/img/app-logo1.png"
                  alt="navbar brand"
                  class="navbar-brand"
                  height="20"
                />
              </a>
              <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                  <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                  <i class="gg-menu-left"></i>
                </button>
              </div>
              <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
              </button>
            </div>
            <!-- End Logo Header -->
          </div>
          <?php include 'components/navbar.php'; ?>
        </div>

        <div class="container">
          <div class="page-inner">
            <div class="page-header">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h3 class="fw-bold mb-2">Standby Attendance</h3>
                  <p class="text-muted mb-0">Mark divers as standby for upcoming operations</p>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                      <h4 class="card-title mb-0">Diver Selection</h4>
                      <div class="text-muted small">
                        <span id="selected-count">0</span> divers selected
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table
                        id="multi-filter-select"
                        class="display table table-striped table-hover"
                      >
                        <thead>
                            <tr>
                                <th width="50px">Select</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Member Since</th>
                                <th>ID</th>
                            </tr>
                        </thead>
                        <tbody id="diver-table-body">
                          <!-- Diver rows will be inserted here -->
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="selection-summary">
                  <h5><i class="fas fa-users me-2"></i>Selected Divers</h5>
                  <div class="mb-3">
                    <ul id="selected-divers-list" class="mb-3">
                      <li class="text-muted">No divers selected yet</li>
                    </ul>
                  </div>
                  
                  <div class="mb-4">
                    <h5><i class="fas fa-ship me-2"></i>Vessel Assignment</h5>
                    <div class="form-group">
                      <label for="vessel-select" class="form-label">Select vessel for standby:</label>
                      <select id="vessel-select" class="form-select" required>
                        <option value="">-- Select Vessel --</option>
                        <!-- Options will be populated by JavaScript -->
                      </select>
                    </div>
                  </div>
                  
                  <div class="d-grid">
                    <button id="mark-standby-btn" class="btn btn-primary" disabled>
                      <i class="fas fa-calendar-check me-2"></i>Mark Standby Attendance
                    </button>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="action-section">
                  <h5><i class="fas fa-file-alt me-2"></i>Report Preparation</h5>
                  <p class="text-muted small mb-3">Assign report preparation duties after marking standby attendance</p>
                  
                  <div class="form-group mb-3">
                    <label for="report-preparation-select" class="form-label">Select employee:</label>
                    <select id="report-preparation-select" class="form-select" disabled>
                      <option value="">-- Select Employee --</option>
                      <!-- Options will be populated by JavaScript -->
                    </select>
                  </div>
                  
                  <div class="d-grid">
                    <button id="assign-report-prep-btn" class="btn btn-secondary" disabled>
                      <i class="fas fa-user-edit me-2"></i>Assign Report Preparation
                    </button>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Current Standby Assignments Section -->
            <div class="row">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                      <h4 class="card-title mb-0">
                        <i class="fas fa-clock me-2"></i>Current Standby Assignments
                      </h4>
                      <!-- <button id="refresh-assignments-btn" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                      </button> -->
                    </div>
                  </div>
                  <div class="card-body">
                    <div id="standby-assignments-container">
                      <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                          <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading standby assignments...</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php include 'components/footer.php'; ?>
        </div>
      </div>
    </div>
    <!--   Core JS Files   -->
    <script src="../assets/js/core/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- jQuery Scrollbar -->
    <script src="../assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>
    <!-- Datatables -->
    <script src="../assets/js/plugin/datatables/datatables.min.js"></script>
    <!-- SweetAlert -->
    <script src="../assets/js/plugin/sweetalert/sweetalert.min.js"></script>
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Kaiadmin JS -->
    <script src="../assets/js/kaiadmin.min.js"></script>
    <!-- Kaiadmin DEMO methods, don't include it in your project! -->
    <script src="../assets/js/setting-demo2.js"></script>
    <script>
     $(document).ready(function () {
    // Global variables
    var selectedDivers = [];
    var diverDataMap = {};
    var diverTable = null;
    var checkedInDivers = [];
    
    // Initialize page
    function initializePage() {
        console.log('Initializing page...');
        loadVessels();
        loadReportPreparationEmployees();
        loadStandbyAssignments();
        loadDiverTable();
    }
    
    // Update selected divers list
    function updateSelectedDiversList() {
        var list = $('#selected-divers-list');
        var countElement = $('#selected-count');
        
        list.empty();
        
        if (selectedDivers.length === 0) {
            list.append('<li class="text-muted">No divers selected yet</li>');
        } else {
            selectedDivers.forEach(function(userID) {
                var diver = diverDataMap[userID];
                if (diver) {
                    list.append(`<li>${diver.fname} ${diver.lname} <span class="text-muted small">(${diver.username})</span></li>`);
                }
            });
        }
        
        countElement.text(selectedDivers.length);
        
        // Enable button only if divers are selected AND vessel is selected
        var vesselSelected = $('#vessel-select').val() !== '';
        var buttonEnabled = selectedDivers.length > 0 && vesselSelected;
        $('#mark-standby-btn').prop('disabled', !buttonEnabled);
    }

    // Load vessels dropdown
    function loadVessels() {
        $.ajax({
            url: '../controllers/getVesselsController.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Vessels response:', response);
                var select = $('#vessel-select');
                select.empty();
                select.append('<option value="">-- Select Vessel --</option>');
                
                if (response.success && Array.isArray(response.data)) {
                    response.data.forEach(function(vessel) {
                        select.append(`<option value="${vessel.vesselID}">${vessel.vessel_name}</option>`);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading vessels:', error);
            }
        });
    }

    // Load employees for report preparation
    function loadReportPreparationEmployees() {
        $.ajax({
            url: '../controllers/getEmployeeForReport.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Employees response:', response);
                var select = $('#report-preparation-select');
                select.empty();
                select.append('<option value="">-- Select Employee --</option>');
                
                if (response.success && Array.isArray(response.data)) {
                    response.data.forEach(function(employee) {
                        select.append(`<option value="${employee.empID}">${employee.name}</option>`);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading employees:', error);
            }
        });
    }

    // Load standby assignments
    function loadStandbyAssignments() {
        $.ajax({
            url: '../controllers/getStandbyAssignmentsController.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Standby assignments response:', response);
                displayStandbyAssignments(response);
                
                // Update checked-in divers list (only those with status = 1)
                if (response.success && response.data) {
                    checkedInDivers = response.data
                        .filter(assignment => assignment.status == 1)
                        .map(assignment => assignment.userID);
                    console.log('Checked-in divers:', checkedInDivers);
                    
                    // Reload diver table to reflect changes
                    loadDiverTable();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading standby assignments:', error);
                displayStandbyAssignments({ success: false, data: [], error: 'Failed to load assignments' });
            }
        });
    }

    // Display standby assignments
    function displayStandbyAssignments(response) {
        var container = $('#standby-assignments-container');
        container.empty();
        
        if (!response.success) {
            container.html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading assignments: ${response.error || 'Unknown error occurred'}
                </div>
            `);
            return;
        }
        
        if (!response.data || response.data.length === 0) {
            container.html(`
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No employees are currently checked in for standby.
                </div>
            `);
            return;
        }
        
        var html = `
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee Name</th>
                            <th>Vessel</th>
                            <th>Duration (Days)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        response.data.forEach(function(assignment) {
            html += `
                <tr>
                    <td>${new Date(assignment.checkInDate).toLocaleDateString()}</td>
                    <td>${assignment.fname} ${assignment.lname}</td>
                    <td>${assignment.vessel_name || 'N/A'}</td>
                    <td>${assignment.duration}</td>
                    <td>
                        <button class="btn btn-sm btn-warning checkout-btn" 
                                data-eaid="${assignment.EAID}" 
                                data-employee-name="${assignment.fname} ${assignment.lname}"
                                data-userid="${assignment.userID}">
                            <i class="fas fa-sign-out-alt me-1"></i> Checkout
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        container.html(html);
    }

    // Load diver data and refresh table
    function loadDiverTable() {
        $.ajax({
            url: '../controllers/getDiversController.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Divers response:', response);
                if (response.success && Array.isArray(response.data)) {
                    // Build diver data map
                    diverDataMap = {};
                    
                    // Filter out checked-in divers
                    var availableDivers = response.data.filter(function(diver) {
                        return checkedInDivers.indexOf(diver.userID) === -1;
                    });
                    
                    availableDivers.forEach(function(diver) {
                        diverDataMap[diver.userID] = diver;
                    });
                    
                    // Refresh the table with available divers only
                    refreshDiverTable();
                    
                    // Clear any selected divers
                    selectedDivers = [];
                    updateSelectedDiversList();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading divers:', error);
            }
        });
    }
    
    // Refresh diver table with available divers only
    function refreshDiverTable() {
        var tbody = $('#diver-table-body');
        tbody.empty();

        Object.values(diverDataMap).forEach(function(diver) {
            var row = `<tr>
                <td><input type="checkbox" class="diver-checkbox" value="${diver.userID}"></td>
                <td>${diver.fname} ${diver.lname}</td>
                <td>${diver.role_name || 'Diver'}</td>
                <td>${diver.username}</td>
                <td>${diver.email}</td>
                <td>${new Date(diver.created_at).toLocaleDateString()}</td>
                <td>${diver.userID}</td>
            </tr>`;
            tbody.append(row);
        });

        // Reinitialize DataTable if needed
        if (diverTable) {
            diverTable.destroy();
        }
        
        diverTable = $('#multi-filter-select').DataTable({
            pageLength: 5,
            initComplete: function() {
                this.api()
                    .columns()
                    .every(function() {
                        var column = this;
                        var select = $(
                            '<select class="form-select"><option value=""></option></select>'
                        )
                            .appendTo($(column.footer()).empty())
                            .on("change", function() {
                                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                                column
                                    .search(val ? "^" + val + "$" : "", true, false)
                                    .draw();
                            });
                        column
                            .data()
                            .unique()
                            .sort()
                            .each(function(d, j) {
                                select.append(
                                    '<option value="' + d + '">' + d + "</option>"
                                );
                            });
                    });
            }
        });
    }
    // Checkout employee
    function checkoutEmployee(EAID, employeeName, userID) {
        swal({
            title: "Confirm Checkout",
            text: `Are you sure you want to checkout ${employeeName}?`,
            icon: "warning",
            buttons: {
                cancel: {
                    text: "Cancel",
                    value: null,
                    visible: true,
                    className: "btn btn-secondary",
                    closeModal: true,
                },
                confirm: {
                    text: "Yes, Checkout",
                    value: true,
                    visible: true,
                    className: "btn btn-warning",
                    closeModal: true
                }
            }
        }).then((willCheckout) => {
            if (willCheckout) {
                swal({
                    title: "Processing Checkout",
                    text: "Please wait...",
                    icon: "info",
                    buttons: false,
                    closeOnClickOutside: false,
                    closeOnEsc: false
                });
                
                $.ajax({
                    url: '../controllers/checkoutStandbyController.php',
                    method: 'POST',
                    data: { EAID: EAID },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            swal({
                                title: "Checkout Successful!",
                                text: `${employeeName} has been checked out successfully.`,
                                icon: "success",
                                buttons: {
                                    confirm: {
                                        className: "btn btn-success"
                                    }
                                }
                            }).then(() => {
                                // Reload all data to ensure consistency
                                loadStandbyAssignments();
                            });
                        } else {
                            swal("Error", response.error || 'Unknown error occurred', "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        swal("Server Error", "An error occurred during checkout.", "error");
                    }
                });
            }
        });
    }

    // Mark divers as standby
    function markDiversStandby() {
        if (selectedDivers.length === 0) {
            swal("Selection Required", "Please select at least one diver", "warning");
            return;
        }

        var vesselID = $('#vessel-select').val();
        if (!vesselID) {
            swal("Vessel Selection Required", "Please select a vessel", "warning");
            return;
        }
        
        $.ajax({
            url: '../controllers/markStandbyAttendanceController.php',
            method: 'POST',
            data: { 
                userIDs: JSON.stringify(selectedDivers),
                vesselID: vesselID
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    sessionStorage.setItem('lastStandbyAttendanceID', response.standbyAttendanceID);
                    
                    if (response.eaidMap) {
                        sessionStorage.setItem('eaidMap', JSON.stringify(response.eaidMap));
                    }
                    
                    swal("Success!", "Attendance recorded successfully!", "success");
                    
                    // Reload all data to ensure consistency
                    loadStandbyAssignments();
                    
                    // Clear selection
                    selectedDivers = [];
                    $('#vessel-select').val('');
                    updateSelectedDiversList();
                    
                    // Enable report preparation
                    $('#report-preparation-select').prop('disabled', false);
                } else {
                    swal("Error", response.error || 'Unknown error', "error");
                }
            },
            error: function(xhr, status, error) {
                swal("Server Error", "Server error occurred.", "error");
            }
        });
    }

    // Event handlers
    $('#report-preparation-select').change(function() {
        $('#assign-report-prep-btn').prop('disabled', $(this).val() === '');
    });

    $('#assign-report-prep-btn').click(function() {
        var empID = $('#report-preparation-select').val();
        var standbyAttendanceID = sessionStorage.getItem('lastStandbyAttendanceID');
        
        if (!empID) {
            swal("Selection Required", "Please select an employee", "warning");
            return;
        }
        
        if (!standbyAttendanceID) {
            swal("No Attendance Record", "No standby attendance record found.", "info");
            return;
        }
        
        $.ajax({
            url: '../controllers/assignReportPreparationController.php',
            method: 'POST',
            data: {
                empID: empID,
                standbyAttendanceID: standbyAttendanceID
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    swal("Success!", "Report preparation assigned successfully!", "success");
                    $('#report-preparation-select').val('');
                    $('#assign-report-prep-btn').prop('disabled', true);
                } else {
                    swal("Error", response.error || 'Unknown error', "error");
                }
            },
            error: function(xhr, status, error) {
                swal("Server Error", "Server error occurred.", "error");
            }
        });
    });

    // Checkbox event
    $('#diver-table-body').on('change', '.diver-checkbox', function() {
        var userID = $(this).val();
        
        if ($(this).is(':checked')) {
            if (!selectedDivers.includes(userID)) selectedDivers.push(userID);
        } else {
            selectedDivers = selectedDivers.filter(function(id) { return id !== userID; });
        }
        
        updateSelectedDiversList();
    });

    // Vessel selection event
    $('#vessel-select').change(function() {
        updateSelectedDiversList();
    });

    // Mark standby button
    $('#mark-standby-btn').click(function() {
        markDiversStandby();
    });

    // Checkout button click handler
    $(document).on('click', '.checkout-btn:not(:disabled)', function(e) {
        e.preventDefault();
        console.log('Checkout button clicked!');
        
        var EAID = $(this).data('eaid');
        var employeeName = $(this).data('employee-name');
        var userID = $(this).data('userid');
        
        console.log('EAID:', EAID, 'Employee:', employeeName, 'UserID:', userID);
        
        if (EAID && employeeName && userID) {
            checkoutEmployee(EAID, employeeName, userID);
        } else {
            console.error('Missing EAID, employee name, or user ID');
            swal("Error", "Missing employee data", "error");
        }
    });

    // Refresh assignments button
    $('#refresh-assignments-btn').click(function() {
        loadStandbyAssignments();
    });

    // Initialize the page
    initializePage();
});
    </script>
  </body>
</html>