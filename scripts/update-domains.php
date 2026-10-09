<?php

declare(strict_types=1);

/** Fetch a public upstream asset, failing before any bundled files are replaced. */
function fetchAsset(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: leancaptain-lara-email\r\nAccept: application/vnd.github+json\r\n",
            'timeout' => 30,
        ],
    ]);
    $contents = file_get_contents($url, false, $context);

    if ($contents === false) {
        throw new RuntimeException('Could not fetch '.$url);
    }

    return $contents;
}

$repository = 'disposable-email-domains/disposable-email-domains';
$revision = json_decode(fetchAsset('https://api.github.com/repos/'.$repository.'/commits/main'), true, flags: JSON_THROW_ON_ERROR);
$commit = $revision['sha'] ?? '';

if (! is_string($commit) || ! preg_match('/^[a-f0-9]{40}$/', $commit)) {
    throw new RuntimeException('The upstream response did not contain a valid commit hash.');
}

$source = 'https://raw.githubusercontent.com/'.$repository.'/'.$commit.'/';
$list = fetchAsset($source.'disposable_email_blocklist.conf');
$license = fetchAsset($source.'LICENSE.txt');
$domains = explode("\n", trim($list));

if (count($domains) < 1000 || count(array_unique($domains)) !== count($domains)) {
    throw new RuntimeException('The upstream domain list is unexpectedly small or contains duplicates.');
}

foreach ($domains as $domain) {
    if ($domain !== strtolower(trim($domain)) || ! str_contains($domain, '.') || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        throw new RuntimeException('The upstream list contains an invalid domain: '.$domain);
    }
}

foreach (['yopmail.com', 'mailinator.com'] as $domain) {
    if (! in_array($domain, $domains, true)) {
        throw new RuntimeException('The upstream list is missing '.$domain);
    }
}

foreach (['gmail.com', 'outlook.com', 'yahoo.com'] as $domain) {
    if (in_array($domain, $domains, true)) {
        throw new RuntimeException('The upstream list unexpectedly blocks '.$domain);
    }
}

if (! str_contains($license, 'CC0')) {
    throw new RuntimeException('The upstream license changed and requires review.');
}

$metadata = json_encode([
    'source' => 'https://github.com/'.$repository,
    'commit' => $commit,
    'domains' => count($domains),
    'sha256' => hash('sha256', $list),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

$root = dirname(__DIR__).'/resources/';
foreach ([
    'disposable-email-domains.txt' => $list,
    'disposable-email-domains.LICENSE' => $license,
    'disposable-email-domains.json' => $metadata,
] as $filename => $contents) {
    if (file_put_contents($root.$filename, $contents) === false) {
        throw new RuntimeException('Could not write '.$filename);
    }
}

fwrite(STDOUT, 'Updated '.count($domains).' domains from '.$commit."\n");
