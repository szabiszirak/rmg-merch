<?php
defined('ABSPATH') || exit;

/**
 * Google ID-token (JWT, RS256) ellenőrzése helyben, a Google nyilvános kulcsaival.
 * Visszaadja a tokenben lévő adatokat (sub, email, name...), vagy RMG_Error-t dob.
 */
function rmg_verify_google_id_token(string $jwt): array {
	$client = rmg_google_client_id();
	if ($client === '') throw new RMG_Error('A Google-belépés még nincs beállítva.', 503);
	$parts = explode('.', $jwt);
	if (count($parts) !== 3) throw new RMG_Error('Érvénytelen belépési adat.', 401);
	[$h64, $p64, $s64] = $parts;
	$header = json_decode(rmg_b64url_decode($h64), true);
	$payload = json_decode(rmg_b64url_decode($p64), true);
	$sig = rmg_b64url_decode($s64);
	if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) throw new RMG_Error('Érvénytelen belépési adat.', 401);
	$keys = rmg_google_keys(false);
	if (!isset($keys[$header['kid']])) $keys = rmg_google_keys(true);
	if (!isset($keys[$header['kid']])) throw new RMG_Error('A belépési adat nem ellenőrizhető.', 401);
	$ok = openssl_verify($h64 . '.' . $p64, $sig, $keys[$header['kid']], OPENSSL_ALGO_SHA256);
	if ($ok !== 1) throw new RMG_Error('Érvénytelen belépési aláírás.', 401);
	$now = time();
	$iss = $payload['iss'] ?? '';
	if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) throw new RMG_Error('Érvénytelen kiállító.', 401);
	if (($payload['aud'] ?? '') !== $client) throw new RMG_Error('A belépés másik alkalmazáshoz tartozik.', 401);
	if ((int) ($payload['exp'] ?? 0) < $now - 60) throw new RMG_Error('A belépés lejárt, próbáld újra.', 401);
	if ((int) ($payload['iat'] ?? 0) > $now + 300) throw new RMG_Error('Érvénytelen belépési idő.', 401);
	if (empty($payload['sub']) || empty($payload['email']) || !($payload['email_verified'] ?? false)) throw new RMG_Error('Ellenőrzött e-mail-címmel rendelkező Google-fiókkal lépj be.', 401);
	return $payload;
}

function rmg_b64url_decode(string $s): string {
	$s = strtr($s, '-_', '+/');
	$pad = strlen($s) % 4;
	if ($pad) $s .= str_repeat('=', 4 - $pad);
	$out = base64_decode($s, true);
	return $out === false ? '' : $out;
}

/** kid => PEM nyilvános kulcs. Gyorsítótárazva; tesztben a 'rmg_google_jwks' szűrővel cserélhető. */
function rmg_google_keys(bool $refresh): array {
	$jwks = apply_filters('rmg_google_jwks', null);
	if (!is_array($jwks)) {
		$cached = $refresh ? false : get_transient('rmg_google_jwks');
		if (is_array($cached)) {
			$jwks = $cached;
		} else {
			$r = wp_remote_get('https://www.googleapis.com/oauth2/v3/certs', ['timeout' => 8]);
			if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) throw new RMG_Error('A Google-kulcsok nem érhetők el. Próbáld újra később.', 503);
			$jwks = json_decode(wp_remote_retrieve_body($r), true);
			if (!is_array($jwks) || empty($jwks['keys'])) throw new RMG_Error('A Google-kulcsok nem érhetők el.', 503);
			$ttl = 3600;
			$cc = wp_remote_retrieve_header($r, 'cache-control');
			if (is_string($cc) && preg_match('/max-age=(\d+)/', $cc, $m)) $ttl = max(300, min(86400, (int) $m[1]));
			set_transient('rmg_google_jwks', $jwks, $ttl);
		}
	}
	$out = [];
	foreach ($jwks['keys'] ?? [] as $k) {
		if (($k['kty'] ?? '') === 'RSA' && !empty($k['kid']) && !empty($k['n']) && !empty($k['e'])) $out[$k['kid']] = rmg_jwk_to_pem($k['n'], $k['e']);
	}
	return $out;
}

/** RSA JWK (n, e) → PEM (SubjectPublicKeyInfo). */
function rmg_jwk_to_pem(string $n64, string $e64): string {
	$n = rmg_b64url_decode($n64);
	$e = rmg_b64url_decode($e64);
	if (ord($n[0]) > 0x7f) $n = "\0" . $n;
	if (ord($e[0]) > 0x7f) $e = "\0" . $e;
	$int = fn(string $v) => "\x02" . rmg_der_len(strlen($v)) . $v;
	$seq = fn(string $v) => "\x30" . rmg_der_len(strlen($v)) . $v;
	$rsa = $seq($int($n) . $int($e));
	$algo = $seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
	$bit = "\x03" . rmg_der_len(strlen($rsa) + 1) . "\0" . $rsa;
	$spki = $seq($algo . $bit);
	return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function rmg_der_len(int $len): string {
	if ($len < 0x80) return chr($len);
	$b = '';
	while ($len > 0) { $b = chr($len & 0xff) . $b; $len >>= 8; }
	return chr(0x80 | strlen($b)) . $b;
}
