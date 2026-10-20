$client = New-Object System.Net.Sockets.TcpClient
$client.Connect('route1.mx.cloudflare.net', 25)
$stream = $client.GetStream()
$reader = New-Object System.IO.StreamReader($stream)
$writer = New-Object System.IO.StreamWriter($stream)
$writer.AutoFlush = $true

function Read-Response {
    $resp = $reader.ReadLine()
    Write-Output "SERVER: $resp"
    while ($resp -match '^\d{3}-') {
        $resp = $reader.ReadLine()
        Write-Output "SERVER: $resp"
    }
    return $resp
}

function Send-Cmd($cmd) {
    Write-Output "CLIENT: $cmd"
    $writer.WriteLine($cmd)
    return Read-Response
}

Read-Response
Send-Cmd "EHLO test.local"
Send-Cmd "MAIL FROM:<>"
Send-Cmd "RCPT TO:<kucing@azkario.biz.id>"
Send-Cmd "DATA"
$writer.WriteLine("From: bounce@test.local")
$writer.WriteLine("To: kucing@azkario.biz.id")
$writer.WriteLine("Subject: Direct Cloudflare MX Null Sender Test")
$writer.WriteLine("")
$writer.WriteLine("Testing null sender to bypass SPF check.")
$writer.WriteLine(".")
Read-Response

Send-Cmd "QUIT"
$client.Close()
