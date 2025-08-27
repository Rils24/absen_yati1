<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Absensi - Pondok Pesantren Yati</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .animate-slideInUp {
            animation: slideInUp 0.6s ease-out;
        }

        .animate-fadeIn {
            animation: fadeIn 0.8s ease-out;
        }

        .animate-pulse-gentle {
            animation: pulse 2s infinite;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .input-focus {
            transition: all 0.3s ease;
        }

        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.15);
        }

        .alert-success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #15803d;
            border: 1px solid #86efac;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fde68a 100%);
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .shadow-custom {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <!-- Background Decorations -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div
            class="absolute -top-40 -right-40 w-80 h-80 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-gentle">
        </div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-gentle"
            style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-blue-200 rounded-full mix-blend-multiply filter blur-xl opacity-50 animate-pulse-gentle"
            style="animation-delay: 2s;"></div>
    </div>

    <!-- Navbar -->
    <nav class="relative z-20 glass-effect shadow-lg sticky top-0">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div
                        class="w-12 h-12 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-graduation-cap text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Pondok Pesantren Yati</h1>
                        <p class="text-sm text-gray-600">Sistem Absensi Digital</p>
                    </div>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="index.php"
                        class="text-blue-600 font-bold transition duration-300 flex items-center space-x-2 border-b-2 border-blue-600">
                        <i class="fas fa-qrcode"></i>
                        <span>Absensi</span>
                    </a>
                    <a href="register.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-user-plus"></i>
                        <span>Registrasi</span>
                    </a>
                    <a href="laporan.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                    </a>
                    <a href="dashboard.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="kelola_siswa.php"
                        class="text-gray-700 hover:text-blue-600 font-medium transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-users"></i>
                        <span>Kelola Siswa</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="relative z-10 px-6 py-8 flex-grow flex items-center justify-center">
        <div
            class="rounded-3xl shadow-custom w-full max-w-xl p-10 text-center animate-slideInUp glass-effect border border-gray-100">
            <h1 class="text-4xl font-extrabold text-gray-800 mb-4 drop-shadow">Sistem Absensi RFID</h1>
            <p class="text-gray-600 text-lg mb-8 font-medium">Silakan tap kartu Anda di reader</p>

            <!-- Input Field -->
            <form id="absensi-form" class="mb-6">
                <input type="text" id="uid_input" placeholder="Menunggu Kartu..."
                    class="w-full border-2 border-gray-200 rounded-xl py-4 px-6 text-center text-2xl focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500 bg-gray-50 input-focus transition duration-300"
                    autofocus />
            </form>

            <!-- Status Message -->
            <div id="status-message"
                class="mt-6 text-xl font-semibold min-h-[60px] rounded-xl shadow transition-all duration-300 flex items-center justify-center p-4 text-gray-700 bg-gray-50">
                <!-- Message will be displayed here -->
            </div>
        </div>
    </main>


    <!-- Script -->
    <script>
        const absensiForm = document.getElementById('absensi-form');
        const uidInput = document.getElementById('uid_input');
        const statusMessage = document.getElementById('status-message');

        let messageTimeout;
        function displayMessage(message, status) {
            // Clear any existing timeout
            if (messageTimeout) {
                clearTimeout(messageTimeout);
            }

            // Update message immediately
            statusMessage.textContent = message;
            statusMessage.className = 'mt-6 text-xl font-semibold animate-fadeIn flex items-center justify-center p-4 rounded-xl shadow transition-all duration-300 ' +
                (status === 'success' ? 'alert-success' :
                    status === 'error' ? 'alert-danger' :
                        status === 'warning' ? 'alert-warning' :
                            'text-gray-700 bg-gray-50');

            // Set new timeout for success/warning messages
            if (status === 'success' || status === 'warning') {
                messageTimeout = setTimeout(() => {
                    statusMessage.textContent = '';
                    statusMessage.className = 'mt-6 text-xl font-semibold min-h-[60px] rounded-xl shadow transition-all duration-300 flex items-center justify-center p-4 text-gray-700 bg-gray-50';
                }, 3000); // Reduced to 3 seconds
            }
        }

        let isProcessing = false;  // Flag to prevent multiple submissions

        absensiForm.addEventListener('submit', function (event) {
            event.preventDefault();

            // Prevent multiple submissions
            if (isProcessing) return;

            const uid = uidInput.value.trim();

            if (uid) {
                isProcessing = true;

                // Clear input immediately after scanning
                uidInput.value = '';

                // Show immediate feedback
                displayMessage('Memproses kartu...', 'info');

                fetch('proses_absen.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'uid=' + encodeURIComponent(uid),
                    timeout: 10000 // 10 second timeout
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        displayMessage(data.message, data.status);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        displayMessage('Terjadi kesalahan saat memproses kartu. Silakan coba lagi.', 'error');
                    })
                    .finally(() => {
                        isProcessing = false;
                        uidInput.focus();
                    });
            }
        });

        // Auto-focus the input field when it's not focused
        setInterval(() => {
            if (!document.activeElement || document.activeElement !== uidInput) {
                uidInput.focus();
            }
        }, 1000);

        // Focus on input field automatically
        document.body.addEventListener('click', () => uidInput.focus());
        uidInput.focus();
    </script>
</body>

</html>