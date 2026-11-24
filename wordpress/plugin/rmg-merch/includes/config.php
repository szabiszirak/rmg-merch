<?php
defined('ABSPATH') || exit;

const RMG_CLASSES = ['7M','8M','9M','10M','11M','12M','9T','10T','11T','12T','9A','10A','11A','12A','9B','10B','11B','12B','9C','10C','11C','12C','9D','10D','11D','12D','9AJTP','Tanár','Öregdiák','Külső'];
const RMG_COLORS = ['Fekete','Fehér'];
const RMG_SIZES = ['S','M','L','XL','XXL'];
const RMG_MAX_PER_LINE = 20;
const RMG_MAX_PER_ORDER = 50;

/** Google OAuth webes kliensazonosító: wp-config RMG_GOOGLE_CLIENT_ID, vagy a beállítások oldal. */
function rmg_google_client_id(): string {
	if (defined('RMG_GOOGLE_CLIENT_ID') && RMG_GOOGLE_CLIENT_ID) return (string) RMG_GOOGLE_CLIENT_ID;
	return (string) get_option('rmg_google_client_id', '');
}

/** Szervezői Google-fiókok: Google-belépéskor automatikusan szervezői (shop_manager) jogot kapnak. */
const RMG_ORGANIZER_EMAILS = ['radnotimerch@gmail.com'];

function rmg_is_organizer_email(string $email): bool {
	return in_array(strtolower(trim($email)), array_map('strtolower', RMG_ORGANIZER_EMAILS), true);
}

function rmg_contact_email(): string {
	$e = (string) get_option('rmg_contact_email', 'radnotimerch@gmail.com');
	return is_email($e) ? $e : 'radnotimerch@gmail.com';
}

/** Rendelési időszak: ['start'=>ISO UTC, 'end'=>ISO UTC, 'pickup'=>string] vagy null. */
function rmg_campaign(): ?array {
	$c = get_option('rmg_campaign');
	if (!is_array($c) || empty($c['start']) || empty($c['end'])) return null;
	return ['start' => (string) $c['start'], 'end' => (string) $c['end'], 'pickup' => (string) ($c['pickup'] ?? '')];
}

function rmg_is_open(?int $now = null): bool {
	$c = rmg_campaign();
	if (!$c) return false;
	$now = $now ?? time();
	return $now >= strtotime($c['start']) && $now < strtotime($c['end']);
}

/** 'YYYY-MM-DDTHH:MM' budapesti idő → ISO UTC. */
function rmg_budapest_to_iso(string $local): string {
	if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $local)) throw new InvalidArgumentException('Hibás időpont.');
	$dt = new DateTimeImmutable($local, new DateTimeZone('Europe/Budapest'));
	return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
}

class RMG_Error extends RuntimeException {
	public int $status;
	public function __construct(string $message, int $status = 400) { parent::__construct($message); $this->status = $status; }
}
