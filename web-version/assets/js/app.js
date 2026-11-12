/**
 * e-Barangay Web Portal - Client-Side Enhancements
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Password visibility toggle
    const toggleBtns = document.querySelectorAll('.btn-toggle-password');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (targetInput) {
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    targetInput.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
            }
        });
    });

    // 2. Service selector dynamic details & RA 11261 form logic in dashboard
    const serviceSelect = document.getElementById('apply_service_id');
    const serviceInfoCard = document.getElementById('service_info_card');
    const ra11261Section = document.getElementById('ra11261_fields_section');
    const feeDisplay = document.getElementById('service_fee_badge');
    const validityDisplay = document.getElementById('service_validity_text');
    const timeDisplay = document.getElementById('service_time_text');
    const requirementsDisplay = document.getElementById('service_requirements_text');
    const isExemptInput = document.getElementById('service_is_exempt');

    if (serviceSelect && serviceInfoCard) {
        function updateServiceForm() {
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                serviceInfoCard.classList.add('d-none');
                if (ra11261Section) ra11261Section.classList.add('d-none');
                return;
            }

            const code = selectedOption.getAttribute('data-code') || '';
            const fee = parseFloat(selectedOption.getAttribute('data-fee') || '0');
            const validity = selectedOption.getAttribute('data-validity') || '';
            const ptime = selectedOption.getAttribute('data-time') || 'Same Day';
            const reqs = selectedOption.getAttribute('data-reqs') || '';

            serviceInfoCard.classList.remove('d-none');

            // Fee badge
            if (feeDisplay) {
                if (fee <= 0 || code === 'CERT-FTJ' || code === 'CERT-IND') {
                    feeDisplay.innerHTML = '<span class="badge bg-success">FREE (Fee Waived)</span>';
                    if (isExemptInput) isExemptInput.value = '1';
                } else {
                    feeDisplay.innerHTML = '<span class="badge bg-primary">₱' + fee.toFixed(2) + '</span>';
                    if (isExemptInput) isExemptInput.value = '0';
                }
            }

            // Validity
            if (validityDisplay) {
                validityDisplay.textContent = validity ? validity + ' days' : 'No fixed expiry';
            }

            // Processing Time
            if (timeDisplay) {
                timeDisplay.textContent = ptime;
            }

            // Requirements
            if (requirementsDisplay) {
                requirementsDisplay.textContent = reqs;
            }

            // Check if RA 11261 First-Time Jobseeker
            if (ra11261Section) {
                if (code === 'CERT-FTJ') {
                    ra11261Section.classList.remove('d-none');
                    // Mark inputs inside required
                    const raCheckboxes = ra11261Section.querySelectorAll('input[type="checkbox"]');
                    raCheckboxes.forEach(cb => cb.required = true);
                } else {
                    ra11261Section.classList.add('d-none');
                    const raCheckboxes = ra11261Section.querySelectorAll('input[type="checkbox"]');
                    raCheckboxes.forEach(cb => cb.required = false);
                }
            }
        }

        serviceSelect.addEventListener('change', updateServiceForm);
        // Initial run
        updateServiceForm();
    }

    // 3. Live Queue Polling for Dashboard
    const liveQueueContainer = document.getElementById('live_queue_section');
    if (liveQueueContainer) {
        function pollQueue() {
            fetch('api/live_queue.php')
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        // Update Now Serving Counter 1
                        const counter1Elem = document.getElementById('counter_1_ticket');
                        if (counter1Elem && data.now_serving_frontdesk !== undefined) {
                            counter1Elem.textContent = data.now_serving_frontdesk || '--';
                        }
                        // Update Now Serving Counter 2
                        const counter2Elem = document.getElementById('counter_2_ticket');
                        if (counter2Elem && data.now_serving_treasurer !== undefined) {
                            counter2Elem.textContent = data.now_serving_treasurer || '--';
                        }
                        // Update Total Waiting
                        const totalWaitingElem = document.getElementById('total_waiting_count');
                        if (totalWaitingElem && data.total_waiting !== undefined) {
                            totalWaitingElem.textContent = data.total_waiting;
                        }

                        // Update Citizen's own ticket if active
                        const myPosElem = document.getElementById('my_queue_position');
                        if (myPosElem && data.my_ticket) {
                            if (data.my_ticket.status === 'Now Serving') {
                                myPosElem.innerHTML = '<span class="badge bg-danger fs-6 p-2"><i class="bi bi-bell-fill"></i> NOW SERVING AT ' + (data.my_ticket.counter_number || 'Counter') + '!</span>';
                            } else if (data.my_ticket.status === 'Waiting') {
                                myPosElem.innerHTML = 'You are <strong>#' + (data.my_position || 1) + '</strong> in line (' + (data.ahead_count || 0) + ' tickets ahead)';
                            }
                        }
                    }
                })
                .catch(err => console.debug('Queue poll error:', err));
        }

        // Poll every 8 seconds
        setInterval(pollQueue, 8000);
    }

    // 4. Copy to clipboard helper
    const copyBtns = document.querySelectorAll('.btn-copy-text');
    copyBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const textToCopy = this.getAttribute('data-clipboard-text') || '';
            if (navigator.clipboard && textToCopy) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);
                });
            }
        });
    });
});
