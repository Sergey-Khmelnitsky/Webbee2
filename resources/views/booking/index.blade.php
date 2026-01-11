<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <label for="service_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Service
                    </label>
                    <select 
                        id="service_id" 
                        name="service_id" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">-- Select a service --</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
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
    </div>

    <script>
        document.getElementById('bookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const serviceId = document.getElementById('service_id').value;
            const date = document.getElementById('date').value;
            
            if (!serviceId || !date) {
                showError('Please select both service and date');
                return;
            }

            // Show loading
            document.getElementById('loadingIndicator').classList.remove('hidden');
            document.getElementById('calendarContainer').classList.add('hidden');
            document.getElementById('errorMessage').classList.add('hidden');
            document.getElementById('loadCalendarBtn').disabled = true;

            try {
                const response = await fetch(`/api/calendar?date=${date}&service_id=${serviceId}`);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to load calendar');
                }

                if (data.success && data.data && data.data.length > 0) {
                    displayCalendar(data.data[0], date);
                } else {
                    showError('No available slots found for the selected date');
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

        function displayCalendar(serviceData, selectedDate) {
            const container = document.getElementById('calendarContent');
            container.innerHTML = '';

            const serviceName = serviceData.name;
            const dateInfo = serviceData.date;
            const slots = dateInfo.slots || [];

            // Service info
            const serviceDiv = document.createElement('div');
            serviceDiv.className = 'bg-white rounded-lg shadow-md p-6 mb-4';
            serviceDiv.innerHTML = `
                <h3 class="text-xl font-semibold text-gray-900 mb-2">${serviceName}</h3>
                <p class="text-gray-600 mb-1">Date: <span class="font-medium">${formatDate(selectedDate)}</span></p>
                <p class="text-gray-600 mb-1">Day: <span class="font-medium">${dateInfo.day_of_week}</span></p>
                <p class="text-sm text-gray-500">Duration: ${serviceData.configuration.duration_minutes} minutes</p>
            `;
            container.appendChild(serviceDiv);

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
                    slotButton.className = 'px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors text-left';
                    slotButton.innerHTML = `
                        <div class="font-medium text-gray-900">${formatTime(slot.start_time)}</div>
                        <div class="text-sm text-gray-500">to ${formatTime(slot.end_time)}</div>
                    `;
                    slotButton.addEventListener('click', () => {
                        alert(`Selected time period: ${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}`);
                        // TODO: Implement booking functionality
                    });
                    slotsGrid.appendChild(slotButton);
                });

                slotsDiv.appendChild(slotsGrid);
                container.appendChild(slotsDiv);
            } else {
                const noSlotsDiv = document.createElement('div');
                noSlotsDiv.className = 'bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded';
                noSlotsDiv.textContent = 'No available time slots for this date.';
                container.appendChild(noSlotsDiv);
            }

            document.getElementById('calendarContainer').classList.remove('hidden');
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
    </script>
</body>
</html>
