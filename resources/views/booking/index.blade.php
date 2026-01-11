<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Book Appointment</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Book Your Appointment</h1>

        <!-- Booking Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form id="bookingForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Select Services
                    </label>
                    <div id="servicesContainer" class="space-y-3">
                        <!-- Service select fields will be added here -->
                    </div>
                    <button 
                        type="button" 
                        id="addServiceBtn"
                        class="mt-2 flex items-center gap-2 text-blue-600 hover:text-blue-700 text-sm font-medium"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Service
                    </button>
                </div>

                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Date
                    </label>
                    <input 
                        type="date" 
                        id="date" 
                        name="date" 
                        required
                        min="{{ date('Y-m-d') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <button 
                    type="submit" 
                    id="loadCalendarBtn"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    Load Calendar
                </button>
            </form>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="hidden text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Loading available slots...</p>
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
            <p id="errorText"></p>
        </div>

        <!-- Calendar Display -->
        <div id="calendarContainer" class="hidden">
            <h2 class="text-2xl font-semibold text-gray-900 mb-4">Available Time Slots</h2>
            <div id="calendarContent" class="space-y-6"></div>
        </div>

        <!-- Booking Modal -->
        <div id="bookingModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Book Appointment</h3>
                        <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div id="modalError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        <p id="modalErrorText"></p>
                    </div>

                    <form id="bookingFormModal">
                        <input type="hidden" id="modalServiceId" name="service_id">
                        <input type="hidden" id="modalDate" name="date">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Select Time Slot <span class="text-red-500">*</span>
                            </label>
                            <div id="timeSlotsList" class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-2">
                                <!-- Time slots will be populated here -->
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="first_name" 
                                name="first_name" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                        </div>

                        <div class="mb-4">
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="last_name" 
                                name="last_name" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                        </div>

                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button 
                                type="button" 
                                id="cancelBookingBtn"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit" 
                                id="submitBookingBtn"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Book Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Services data from backend
        const servicesData = @json($services);
        let serviceCounter = 0;

        // Initialize with one service select
        function initializeServices() {
            addServiceSelect();
        }

        // Add a new service select field
        function addServiceSelect(selectedServiceId = '') {
            const container = document.getElementById('servicesContainer');
            const serviceDiv = document.createElement('div');
            serviceDiv.className = 'flex items-center gap-2';
            serviceDiv.dataset.serviceIndex = serviceCounter++;

            const select = document.createElement('select');
            select.name = 'service_ids[]';
            select.className = 'flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
            select.required = true;

            // Add default option
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = '-- Select a service --';
            select.appendChild(defaultOption);

            // Add services
            servicesData.forEach(service => {
                const option = document.createElement('option');
                option.value = service.id;
                option.textContent = service.name;
                if (selectedServiceId && service.id == selectedServiceId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            // Remove button (only show if more than one service)
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-red-600 hover:text-red-700 p-2 hidden';
            removeBtn.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            `;
            removeBtn.addEventListener('click', () => {
                serviceDiv.remove();
                updateRemoveButtons();
            });

            serviceDiv.appendChild(select);
            serviceDiv.appendChild(removeBtn);
            container.appendChild(serviceDiv);

            updateRemoveButtons();
        }

        // Update remove buttons visibility
        function updateRemoveButtons() {
            const serviceSelects = document.querySelectorAll('#servicesContainer > div');
            serviceSelects.forEach(div => {
                const removeBtn = div.querySelector('button');
                if (serviceSelects.length > 1) {
                    removeBtn.classList.remove('hidden');
                } else {
                    removeBtn.classList.add('hidden');
                }
            });
        }

        // Add service button handler
        document.getElementById('addServiceBtn').addEventListener('click', () => {
            addServiceSelect();
        });

        // Initialize on page load
        initializeServices();

        // Form submission
        document.getElementById('bookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Get selected service IDs
            const serviceSelects = document.querySelectorAll('select[name="service_ids[]"]');
            const selectedServiceIds = Array.from(serviceSelects)
                .map(select => select.value)
                .filter(id => id !== '');

            const date = document.getElementById('date').value;
            
            if (selectedServiceIds.length === 0 || !date) {
                showError('Please select at least one service and a date');
                return;
            }

            // Show loading
            document.getElementById('loadingIndicator').classList.remove('hidden');
            document.getElementById('calendarContainer').classList.add('hidden');
            document.getElementById('errorMessage').classList.add('hidden');
            document.getElementById('loadCalendarBtn').disabled = true;

            try {
                // Build query string with service_ids array
                const serviceIdsParam = selectedServiceIds.map(id => `service_ids[]=${id}`).join('&');
                const url = `/api/calendar?date=${date}&${serviceIdsParam}`;

                const response = await fetch(url);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to load calendar');
                }

                if (data.success && data.data && data.data.length > 0) {
                    // If multiple services, data will contain common slots for all services
                    displayCalendars(data.data, date);
                } else {
                    showError('No available slots found for the selected date and services');
                }
            } catch (error) {
                console.error('Error loading calendar:', error);
                showError(error.message || 'Failed to load calendar. Please try again.');
            } finally {
                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('loadCalendarBtn').disabled = false;
            }
        });

        function showError(message) {
            document.getElementById('errorText').textContent = message;
            document.getElementById('errorMessage').classList.remove('hidden');
        }

        function displayCalendars(calendarDataArray, selectedDate) {
            const container = document.getElementById('calendarContent');
            container.innerHTML = '';

            calendarDataArray.forEach(serviceData => {
                displayCalendar(serviceData, selectedDate, container);
            });

            document.getElementById('calendarContainer').classList.remove('hidden');
        }

        function displayCalendar(serviceData, selectedDate, container = null) {
            if (!container) {
                container = document.getElementById('calendarContent');
            }

            const serviceName = serviceData.name;
            const dateInfo = serviceData.date;
            const slots = dateInfo.slots || [];

            // Service section wrapper
            const serviceSection = document.createElement('div');
            serviceSection.className = 'mb-6';

            // Service info
            const serviceDiv = document.createElement('div');
            serviceDiv.className = 'bg-white rounded-lg shadow-md p-6 mb-4';
            serviceDiv.innerHTML = `
                <h3 class="text-xl font-semibold text-gray-900 mb-2">${serviceName}</h3>
                <p class="text-gray-600 mb-1">Date: <span class="font-medium">${formatDate(selectedDate)}</span></p>
                <p class="text-gray-600 mb-1">Day: <span class="font-medium">${dateInfo.day_of_week}</span></p>
                <p class="text-sm text-gray-500">Duration: ${serviceData.configuration.duration_minutes} minutes</p>
            `;
            serviceSection.appendChild(serviceDiv);

            // Available time periods
            if (slots.length > 0) {
                const slotsDiv = document.createElement('div');
                slotsDiv.className = 'bg-white rounded-lg shadow-md p-6';
                
                const slotsTitle = document.createElement('h3');
                slotsTitle.className = 'text-lg font-semibold text-gray-900 mb-4';
                slotsTitle.textContent = 'Available Time Periods';
                slotsDiv.appendChild(slotsTitle);

                const slotsGrid = document.createElement('div');
                slotsGrid.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3';

                slots.forEach(slot => {
                    const slotButton = document.createElement('button');
                    slotButton.type = 'button';
                    slotButton.className = 'px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors text-left';
                    slotButton.innerHTML = `
                        <div class="font-medium text-gray-900">${formatTime(slot.start_time)}</div>
                        <div class="text-sm text-gray-500">to ${formatTime(slot.end_time)}</div>
                    `;
                    slotButton.addEventListener('click', () => {
                        openBookingModal(slot, serviceData, selectedDate);
                    });
                    slotsGrid.appendChild(slotButton);
                });

                slotsDiv.appendChild(slotsGrid);
                serviceSection.appendChild(slotsDiv);
            } else {
                const noSlotsDiv = document.createElement('div');
                noSlotsDiv.className = 'bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded';
                noSlotsDiv.textContent = 'No available time slots for this date.';
                serviceSection.appendChild(noSlotsDiv);
            }

            container.appendChild(serviceSection);
        }

        function formatDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }

        function formatTime(timeString) {
            // timeString is in format "HH:mm" or "HH:mm:ss"
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours, 10);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${displayHour}:${minutes} ${ampm}`;
        }

        // Set minimum date to today
        document.getElementById('date').min = new Date().toISOString().split('T')[0];

        // Modal functions
        let currentPeriod = null;
        let currentServiceData = null;
        let currentSelectedDate = null;

        function openBookingModal(period, serviceData, selectedDate) {
            currentPeriod = period;
            currentServiceData = serviceData;
            currentSelectedDate = selectedDate;

            // Set hidden fields
            document.getElementById('modalServiceId').value = serviceData.id;
            document.getElementById('modalDate').value = selectedDate;

            // Calculate available slots in this period
            const slots = calculateSlotsInPeriod(
                period.start_time,
                period.end_time,
                serviceData.configuration.duration_minutes,
                serviceData.configuration.break_between_minutes
            );

            // Populate time slots list
            const slotsList = document.getElementById('timeSlotsList');
            slotsList.innerHTML = '';

            if (slots.length === 0) {
                slotsList.innerHTML = '<p class="text-gray-500 text-sm">No available slots in this period</p>';
            } else {
                slots.forEach((slot, index) => {
                    const slotDiv = document.createElement('div');
                    slotDiv.className = 'flex items-center p-2 border border-gray-200 rounded hover:bg-gray-50 cursor-pointer';
                    
                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'time_slot';
                    radio.id = `slot_${index}`;
                    radio.value = `${slot.start}-${slot.end}`;
                    radio.required = true;
                    radio.className = 'mr-3';

                    const label = document.createElement('label');
                    label.htmlFor = `slot_${index}`;
                    label.className = 'flex-1 cursor-pointer';
                    label.textContent = `${formatTime(slot.start)} - ${formatTime(slot.end)}`;

                    slotDiv.appendChild(radio);
                    slotDiv.appendChild(label);
                    slotsList.appendChild(slotDiv);
                });
            }

            // Reset form
            document.getElementById('bookingFormModal').reset();
            document.getElementById('modalError').classList.add('hidden');

            // Show modal
            document.getElementById('bookingModal').classList.remove('hidden');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
            document.getElementById('modalError').classList.add('hidden');
            currentPeriod = null;
            currentServiceData = null;
            currentSelectedDate = null;
        }

        function calculateSlotsInPeriod(startTime, endTime, durationMinutes, breakBetweenMinutes) {
            const slots = [];
            const start = parseTime(startTime);
            const end = parseTime(endTime);
            const slotInterval = durationMinutes + breakBetweenMinutes;

            let currentSlotStart = start;
            
            while (currentSlotStart + durationMinutes <= end) {
                const slotStart = formatTimeFromMinutes(currentSlotStart);
                const slotEnd = formatTimeFromMinutes(currentSlotStart + durationMinutes);
                
                slots.push({
                    start: slotStart,
                    end: slotEnd,
                    startMinutes: currentSlotStart,
                    endMinutes: currentSlotStart + durationMinutes
                });

                currentSlotStart += slotInterval;
            }

            return slots;
        }

        function parseTime(timeString) {
            // timeString is in format "HH:mm"
            const [hours, minutes] = timeString.split(':').map(Number);
            return hours * 60 + minutes;
        }

        function formatTimeFromMinutes(totalMinutes) {
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        }

        // Close modal handlers
        document.getElementById('closeModalBtn').addEventListener('click', closeBookingModal);
        document.getElementById('cancelBookingBtn').addEventListener('click', closeBookingModal);

        // Close modal on outside click
        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBookingModal();
            }
        });

        // Handle form submission
        document.getElementById('bookingFormModal').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const selectedSlot = formData.get('time_slot');
            
            if (!selectedSlot) {
                showModalError('Please select a time slot');
                return;
            }

            const [slotStart, slotEnd] = selectedSlot.split('-');
            const bookingData = {
                service_id: parseInt(formData.get('service_id')),
                date: formData.get('date'),
                start_time: `${formData.get('date')} ${slotStart}:00`,
                end_time: `${formData.get('date')} ${slotEnd}:00`,
                participants: [{
                    first_name: formData.get('first_name'),
                    last_name: formData.get('last_name'),
                    email: formData.get('email')
                }]
            };

            // Disable submit button
            const submitBtn = document.getElementById('submitBookingBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Booking...';

            try {
                const response = await fetch('/api/bookings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(bookingData)
                });

                const data = await response.json();

                if (response.ok) {
                    alert('Appointment booked successfully!');
                    closeBookingModal();
                    // Optionally reload the calendar
                    document.getElementById('bookingForm').dispatchEvent(new Event('submit'));
                } else {
                    showModalError(data.message || 'Failed to book appointment. Please try again.');
                }
            } catch (error) {
                console.error('Error booking appointment:', error);
                showModalError('An error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Book Appointment';
            }
        });

        function showModalError(message) {
            document.getElementById('modalErrorText').textContent = message;
            document.getElementById('modalError').classList.remove('hidden');
        }
    </script>
</body>
</html>
