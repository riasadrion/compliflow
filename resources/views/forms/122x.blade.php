<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Form 122X — Service Summary</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 16px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px 10px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>NYS ACCES-VR — Service Summary (122X)</h1>
    <table>
        <thead>
            <tr>
                <th>Client Name</th>
                <th>DOB</th>
                <th>Service Date</th>
                <th>Service Code</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
            <tr>
                <td>{{ $row['client_name'] }}</td>
                <td>{{ $row['dob'] }}</td>
                <td>{{ $row['service_date'] }}</td>
                <td>{{ $row['service_code'] }}</td>
                <td>{{ $row['notes'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
