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

    // Load standby assignments
    function loadStandbyAssignments() {
        $.ajax({
            url: '../controllers/getStandbyAssignmentsController.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Standby assignments response:', response);
                displayStandbyAssignments(response);
                
                // Update checked-in divers list
                if (response.success && response.data) {
                    // Properly extract userIDs from the response
                    checkedInDivers = response.data.map(assignment => {
                        return assignment.userID ? assignment.userID.toString() : null;
                    }).filter(id => id !== null);
                    
                    console.log('Checked-in divers:', checkedInDivers);
                    
                    // After updating checked-in list, refresh the diver table
                    refreshDiverTable();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading standby assignments:', error);
                displayStandbyAssignments({ success: false, data: [], error: 'Failed to load assignments' });
            }
        });
    }

    // Refresh diver table based on current checked-in status
    function refreshDiverTable() {
        var diversToShow = [];
        
        // Filter out checked-in divers
        Object.values(diverDataMap).forEach(function(diver) {
            if (checkedInDivers.indexOf(diver.userID.toString()) === -1) {
                diversToShow.push(diver);
            }
        });
        
        console.log('Refreshing diver table with:', diversToShow);
        
        var tbody = $('#diver-table-body');
        tbody.empty();
        
        diversToShow.forEach(function(diver) {
            var row = `<tr>
                <td><input type="checkbox" class="diver-checkbox" value="${diver.userID}"></td>
                <td>${diver.fname} ${diver.lname}</td>
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
                                // Remove from checked-in list
                                checkedInDivers = checkedInDivers.filter(id => id !== userID.toString());
                                console.log('After checkout - checkedInDivers:', checkedInDivers);
                                
                                // Refresh both tables
                                refreshDiverTable();
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
                    
                    // Add to checked-in list (ensure we're adding strings)
                    selectedDivers.forEach(userID => {
                        if (checkedInDivers.indexOf(userID.toString()) === -1) {
                            checkedInDivers.push(userID.toString());
                        }
                    });
                    console.log('After check-in - checkedInDivers:', checkedInDivers);
                    
                    // Refresh both tables
                    refreshDiverTable();
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

    // Rest of your code remains the same...
    // (Event handlers, other functions, etc.)
    
    // Initialize the page
    initializePage();
});