<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Review Supplier</title>
</head>

<body>
    <h2>Hasil Review Supplier</h2>
    <p>Halo,</p>
    <p>Supplier {{$nama->nama_personal}} mendapatkan review <span style="color: red;">bermasalah</span> dengan catatan lawyer sebagai berikut:</p>

    @if ($status->status_3_keterangan)
    <p><strong>Dengan Keterangan:</strong> </p>
    <p>{{ $status->status_3_keterangan }}</p>
    @endif

    <p><i>Silakan cek lampiran email ini untuk file pendukung.</i></p>
</body>

</html>