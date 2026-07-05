{{-- resources/views/emails/customer_submitted.blade.php --}}

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Pengajuan Customer Baru</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .content p {
            margin: 0 0 10px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 20px;
            background-color: #007bff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
        <h2 class="header">
            Pengajuan Customer Baru
        </h2>
        <div class="content">
            <p>Yth. Bapak/Ibu,</p>
            <p>
                Anda menerima notifikasi ini karena ada pengajuan data customer baru yang perlu ditinjau.
            </p>
            <p>
                <strong>Nama Customer:</strong> {{ $customer->nama_perusahaan }} <br>
                <strong>Tanggal Pengajuan:</strong> {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i') }} WIB
            </p>
            <p>
                Silakan klik tombol di bawah ini untuk melihat detail dan melakukan verifikasi.
            </p>
            <a href="{{ route('customer.show', $customer->id) }}" class="button">Lihat Detail Customer</a>
        </div>
        <div class="footer">
            <p>Ini adalah email otomatis. Mohon untuk tidak membalas email ini.</p>
        </div>
</body>
</html>