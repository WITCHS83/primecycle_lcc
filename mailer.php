<?php
/**
 * mailer.php — Envio de e-mail SMTP/MAIL com HTML+Texto, CC/BCC e anexos
 * Uso:
 *   require_once __DIR__.'/mailer.php';
 *   $debug='';
 *   $ok = send_mail([
 *     'to'         => ['destino@exemplo.com' => 'Destinatário'],
 *     'subject'    => 'Teste',
 *     'html'       => '<h1>Olá</h1><p>HTML</p>',
 *     'text'       => "Olá\nTexto",
 *     'from_email' => 'no-reply@seu-dominio.com',
 *     'from_name'  => 'Lifecycle Canvas',
 *     // SMTP (opcional; se ausente, tenta mail()):
 *     'smtp' => [
 *       'host'   => 'smtp.seu-dominio.com',
 *       'port'   => 587,              // 587(TLS) | 465(SSL) | 25/2525(None)
 *       'secure' => 'tls',            // 'tls' | 'ssl' | 'none'
 *       'user'   => 'usuario@seu-dominio.com',
 *       'pass'   => 'SUA_SENHA'
 *     ],
 *     // cc, bcc, attachments => opcionais
 *   ], $debug);
 *   if(!$ok){ echo "<pre>$debug</pre>"; }
 */

if (!function_exists('send_mail')) {

  function _qp_encode($str) {
    return preg_replace('/[^\x09\x20\x21-\x3C\x3E-\x7E]/e', 'sprintf("=%02X", ord("$0"))', $str);
  }
  function _normalize_eol($s) {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return str_replace("\n", "\r\n", $s);
  }
  function _addr_list($arr) {
    $out = [];
    foreach ($arr as $k=>$v) {
      if (is_int($k)) { $out[] = "<{$v}>"; }
      else {
        $name = mb_encode_mimeheader($v,'UTF-8');
        $out[] = "{$name} <{$k}>";
      }
    }
    return implode(', ', $out);
  }
  function _readline($fp, &$dbg) { $line=fgets($fp,2048); $dbg[]='S: '.rtrim((string)$line); return $line; }
  function _writeline($fp, &$dbg, $line) { $dbg[]='C: '.$line; fwrite($fp, $line."\r\n"); }

  function send_mail(array $opts, string &$debug = ''): bool {
    $debug=''; $dbg=[];
    $to = $opts['to'] ?? []; if(!$to){ $debug='Sem destinatário.'; return false; }
    $subject = (string)($opts['subject'] ?? ''); if($subject===''){ $debug='Sem assunto.'; return false; }
    $html = (string)($opts['html'] ?? ''); $text = (string)($opts['text'] ?? '');
    if ($html==='' && $text==='') $text=' ';

    $fromEmail = (string)($opts['from_email'] ?? '');
    $fromName  = (string)($opts['from_name']  ?? '');
    $cc  = $opts['cc']  ?? []; $bcc = $opts['bcc'] ?? [];
    $atts = $opts['attachments'] ?? [];
    $smtp = $opts['smtp'] ?? null;

    $bMixed = 'bMIXED_'.bin2hex(random_bytes(8));
    $bAlt   = 'bALT_'.bin2hex(random_bytes(8));

    $subjectEnc = '=?UTF-8?B?'.base64_encode($subject).'?=';
    $fromNameEnc = $fromName ? mb_encode_mimeheader($fromName,'UTF-8') : null;

    $headers = [];
    $headers[]='MIME-Version: 1.0';
    $headers[]='From: '.($fromNameEnc ? "{$fromNameEnc} <{$fromEmail}>" : $fromEmail);
    $headers[]='Reply-To: '.$fromEmail;
    if(!empty($cc))  $headers[]='Cc: '._addr_list($cc);
    if(!empty($bcc)) $headers[]='Bcc: '._addr_list($bcc);

    $hasAtt = !empty($atts);
    $headers[] = $hasAtt
      ? 'Content-Type: multipart/mixed; boundary="'.$bMixed.'"'
      : 'Content-Type: multipart/alternative; boundary="'.$bAlt.'"';

    $body='';
    if($hasAtt){
      $body.="--{$bMixed}\r\n";
      $body.='Content-Type: multipart/alternative; boundary="'.$bAlt."\"\r\n\r\n";
    }

    if ($text==='' && $html!=='') $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    if ($text==='') $text=' ';

    $body.="--{$bAlt}\r\n";
    $body.="Content-Type: text/plain; charset=UTF-8\r\n";
    $body.="Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $body.=_normalize_eol(_qp_encode($text))."\r\n";

    if($html!==''){
      $body.="--{$bAlt}\r\n";
      $body.="Content-Type: text/html; charset=UTF-8\r\n";
      $body.="Content-Transfer-Encoding: 8bit\r\n\r\n";
      $body.= _normalize_eol($html)."\r\n";
    }
    $body.="--{$bAlt}--\r\n";

    if($hasAtt){
      foreach($atts as $att){
        $filename=null;$content=null;$mime=null;
        if(is_string($att)){
          if(!is_file($att)||!is_readable($att)){ $dbg[]="Anexo ignorado: {$att}"; continue; }
          $filename=basename($att); $content=file_get_contents($att);
          $mime = mime_content_type($att) ?: 'application/octet-stream';
        } elseif(is_array($att)){
          $filename=$att['filename']??('anexo_'.bin2hex(random_bytes(3)));
          $content =$att['content'] ?? '';
          $mime    =$att['mime'] ?? 'application/octet-stream';
        }
        if($content===null) continue;
        $body.="--{$bMixed}\r\n";
        $body.="Content-Type: {$mime}; name=\"".addcslashes($filename,'"')."\"\r\n";
        $body.="Content-Transfer-Encoding: base64\r\n";
        $body.="Content-Disposition: attachment; filename=\"".addcslashes($filename,'"')."\"\r\n\r\n";
        $body.= chunk_split(base64_encode($content))."\r\n";
      }
      $body.="--{$bMixed}--\r\n";
    }

    // SMTP
    if(is_array($smtp) && !empty($smtp['host'])){
      $host=(string)$smtp['host']; $port=(int)($smtp['port']??587);
      $secure=strtolower((string)($smtp['secure']??'tls')); // tls|ssl|none
      $user=(string)($smtp['user']??''); $pass=(string)($smtp['pass']??'');
      if(!$fromEmail){ $debug='from_email obrigatório para SMTP.'; return false; }

      $remote = ($secure==='ssl')?"ssl://{$host}":$host;
      $dbg[]="Conectando {$remote}:{$port}";
      $fp=@stream_socket_client("{$remote}:{$port}", $errno, $errstr, 20);
      if(!$fp){ $debug="Falha conectar: $errno $errstr"; return false; }
      stream_set_timeout($fp,20);

      $banner=_readline($fp,$dbg); if(!$banner||$banner[0]!=='2'){ $debug="Sem banner 220"; fclose($fp); return false; }
      _writeline($fp,$dbg,'EHLO localhost'); _readline($fp,$dbg);

      if($secure==='tls'){
        _writeline($fp,$dbg,'STARTTLS'); $r=_readline($fp,$dbg);
        if(strpos($r,'220')!==0){ $debug="STARTTLS não aceito"; fclose($fp); return false; }
        if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){ $debug="Falha no TLS"; fclose($fp); return false; }
        _writeline($fp,$dbg,'EHLO localhost'); _readline($fp,$dbg);
      }

      if($user!=='' || $pass!==''){
        _writeline($fp,$dbg,'AUTH LOGIN'); $r=_readline($fp,$dbg); if(strpos($r,'334')!==0){ $debug="AUTH LOGIN não aceito"; fclose($fp); $debug=implode("\n",$dbg); return false; }
        _writeline($fp,$dbg,base64_encode($user)); $r=_readline($fp,$dbg); if(strpos($r,'334')!==0){ $debug="Usuário rejeitado"; fclose($fp); $debug=implode("\n",$dbg); return false; }
        _writeline($fp,$dbg,base64_encode($pass)); $r=_readline($fp,$dbg); if(strpos($r,'235')!==0){ $debug="Senha rejeitada"; fclose($fp); $debug=implode("\n",$dbg); return false; }
      }

      _writeline($fp,$dbg,"MAIL FROM:<{$fromEmail}>"); $r=_readline($fp,$dbg); if(strpos($r,'250')!==0){ $debug="MAIL FROM rejeitado"; fclose($fp); $debug=implode("\n",$dbg); return false; }

      foreach (['to'=>$to,'cc'=>$cc,'bcc'=>$bcc] as $set){
        foreach($set as $k=>$v){
          $email = is_int($k)?$v:$k;
          _writeline($fp,$dbg,"RCPT TO:<{$email}>");
          $r=_readline($fp,$dbg); if(strpos($r,'250')!==0 && strpos($r,'251')!==0){ $debug="RCPT rejeitado: {$email}"; fclose($fp); $debug=implode("\n",$dbg); return false; }
        }
      }

      _writeline($fp,$dbg,'DATA'); $r=_readline($fp,$dbg); if(strpos($r,'354')!==0){ $debug="DATA rejeitado"; fclose($fp); $debug=implode("\n",$dbg); return false; }

      $headerLines=$headers;
      $headerLines[]='To: '._addr_list($to);
      if(!empty($cc)) $headerLines[]='Cc: '._addr_list($cc);
      $headerLines[]="Subject: {$subjectEnc}";

      $data = implode("\r\n",$headerLines)."\r\n\r\n".$body;
      $data = _normalize_eol($data);
      $data = preg_replace("/\r\n\./","\r\n..",$data);

      fwrite($fp, $data."\r\n.\r\n");
      $r=_readline($fp,$dbg); if(strpos($r,'250')!==0){ $debug="Mensagem não aceita"; fclose($fp); $debug=implode("\n",$dbg); return false; }

      _writeline($fp,$dbg,'QUIT'); _readline($fp,$dbg); fclose($fp);
      $debug=implode("\n",$dbg);
      return true;
    }

    // mail()
    $header = implode("\r\n",$headers);
    $toHeader = _addr_list($to);
    $ok = @mail($toHeader, $subjectEnc, $body, $header);
    if(!$ok){ $debug="mail() retornou false (pode estar bloqueado na hospedagem)."; }
    return $ok;
  }
}
