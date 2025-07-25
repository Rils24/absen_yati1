<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Absensi - Pondok Pesantren Yati</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fade-in 0.4s ease-out;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col font-sans bg-gradient-to-br from-blue-600 to-blue-900">
    <!-- Background Image -->
    <div class="absolute inset-0 -z-10">
        <img src="foto-pondok.jpg" alt="Pondok Pesantren Yati"
            class="w-full h-full object-cover object-center brightness-90" />
        <div class="absolute inset-0 bg-blue-900 bg-opacity-60"></div>
    </div>

    <!-- Navbar -->
    <nav class="bg-blue-800 bg-opacity-80 text-white shadow-lg sticky top-0 z-20 backdrop-blur-md">
        <div class="container mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center gap-3">
                <img src="foto-pondok.jpg" alt="Logo"
                    class="w-10 h-10 rounded-full border-2 border-white shadow-md object-cover" />
                <span class="text-2xl font-bold drop-shadow">Pondok Pesantren Yati</span>
            </div>
            <ul class="flex gap-6 text-lg">
                <li><a href="index.php" class="hover:text-yellow-300 font-medium transition">Absensi</a></li>
                <li><a href="register.php" class="hover:text-yellow-300 font-medium transition">Registrasi</a></li>
                <li><a href="laporan.php" class="hover:text-yellow-300 font-medium transition">Laporan</a></li>
                <li><a href="dashboard.php" class="hover:text-yellow-300 font-medium transition">Dashboard</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-6">
        <div
            class="rounded-3xl shadow-2xl w-full max-w-xl p-10 text-center fade-in backdrop-blur-md border-2 border-blue-500 bg-white/80">
            <h1 class="text-4xl font-extrabold text-blue-700 mb-2 drop-shadow">Sistem Absensi RFID</h1>
            <p class="text-blue-900 text-lg mb-6 font-medium">Silakan tap kartu Anda di reader</p>
            <!-- Input Field -->
            <form id="absensi-form">
                <input type="text" id="uid_input" placeholder="Menunggu Kartu..."
                    class="w-full border-2 border-blue-500 bg-blue-50 text-blue-900 rounded-xl py-4 px-6 text-center text-2xl focus:outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-300 shadow-inner transition duration-200"
                    autofocus />
            </form>
            <!-- Status Message -->
            <div id="status-message"
                class="mt-6 text-xl font-semibold min-h-[60px] rounded-xl bg-blue-50/80 text-blue-800 shadow transition-all duration-300 flex items-center justify-center p-4">
            </div>
        </div>
    </main>

    <!-- Script -->
    <script>
        const absensiForm = document.getElementById('absensi-form');
        const uidInput = document.getElementById('uid_input');
        const statusMessage = document.getElementById('status-message');

        function displayMessage(message, status) {
            statusMessage.textContent = message;
            statusMessage.className = 'mt-6 text-xl font-semibold fade-in flex items-center justify-center p-4 ' +
                (status === 'success' ? 'bg-green-100 text-green-700' :
                    status === 'error' ? 'bg-red-100 text-red-700' :
                        status === 'warning' ? 'bg-yellow-100 text-yellow-700' :
                            'bg-blue-50/80 text-blue-800'); // Default or info
            
            // Clear message after 5 seconds
            setTimeout(() => {
                statusMessage.textContent = '';
                statusMessage.className = 'mt-6 text-xl font-semibold min-h-[60px] rounded-xl bg-blue-50/80 text-blue-800 shadow transition-all duration-300 flex items-center justify-center p-4';
            }, 5000);
        }

        absensiForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const uid = uidInput.value.trim();

            if (uid) {
                fetch('proses_absen.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'uid=' + encodeURIComponent(uid)
                })
                    .then(response => response.json())
                    .then(data => {
                        displayMessage(data.message, data.status);
                        uidInput.value = ''; // Clear input after processing
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        displayMessage('Terjadi kesalahan koneksi.', 'error');
                    });
            }
        });

        // Focus on input field automatically
        document.body.addEventListener('click', () => uidInput.focus());
        uidInput.focus();
    </script>
</body>

</html>
