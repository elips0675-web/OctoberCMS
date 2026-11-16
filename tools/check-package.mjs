#!/usr/bin/env node
/**
 * Упаковка продукта: тема и плагин как товар (P2-26, Stage 37).
 *
 * Что проверяется:
 *   - `themes/elv/composer.json` существует, валиден и его `name` после
 *     преобразования ядра (`System::composerToOctoberCode()`) совпадает
 *     с `authorCode.code` из `themes/elv/theme.yaml`
 *     (`modules/cms/classes/ThemeManager.php:229-231`, `:354-366`);
 *   - `authorCode` в `theme.yaml` есть, `version.yaml` темы содержит версию —
 *     без них тема не опознаётся как продукт и не обновляется
 *     (`ThemeManager.php:371-393`);
 *   - `screenshot.png` темы существует, это PNG и не пустой
 *     (требование карточки Marketplace, ядром не используется);
 *   - `LICENSE.md` есть в теме и в плагине, тексты совпадают
 *     (`"license": "proprietary"` в `composer.json` без файла лицензии
 *     противоречит продаже);
 *   - `composer.json` плагина — форма `vendor/name`, тип `october-plugin`;
 *   - в поставляемом коде нет абсолютных путей вида `D:/` или `C:\`
 *     (`plugins/elv/library`, `themes/elv`, `config/*.php`) — локальный путь
 *     в `Import.php` был ровно один и удалён в этом же этапе.
 *
 *   node tools/check-package.mjs
 *
 * Сервер не нужен.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const themeDir = path.join(root, 'themes/elv');
const pluginDir = path.join(root, 'plugins/elv/library');
const failures = [];

function fail(message) {
    failures.push(message);
}

function readJson(file) {
    if (!fs.existsSync(file)) {
        fail(`${path.relative(root, file)}: файла нет`);

        return null;
    }

    try {
        return JSON.parse(fs.readFileSync(file, 'utf8'));
    }
    catch (error) {
        fail(`${path.relative(root, file)}: невалидный JSON (${error.message})`);

        return null;
    }
}

function yamlValue(file, key) {
    if (!fs.existsSync(file)) {
        return null;
    }

    const match = new RegExp(`^${key}\\s*:\\s*(.+)$`, 'm').exec(fs.readFileSync(file, 'utf8'));

    return match ? match[1].trim().replace(/^['"]|['"]$/g, '') : null;
}

function octoberCode(composerName) {
    let name = String(composerName || '').toLowerCase();
    name = name.split('-plugin')[0] || name;
    name = name.split('-theme')[0] || name;

    const parts = name.split('/');
    const vendor = parts[0] || '';
    const packageName = (parts[1] || '').replace(/^oc-/, '');

    return `${vendor}.${packageName}`.replace(/\.+$/, '');
}

function checkTheme() {
    const composer = readJson(path.join(themeDir, 'composer.json'));
    const authorCode = yamlValue(path.join(themeDir, 'theme.yaml'), 'authorCode');
    const code = yamlValue(path.join(themeDir, 'theme.yaml'), 'code');

    if (!authorCode) {
        fail('themes/elv/theme.yaml: нет authorCode');
    }

    if (!code) {
        fail('themes/elv/theme.yaml: нет code');
    }

    if (composer && authorCode && code) {
        if (composer.type !== 'october-theme') {
            fail(`themes/elv/composer.json: type «${composer.type || '—'}» вместо october-theme`);
        }

        const expected = `${authorCode}.${code}`.toLowerCase();
        const actual = octoberCode(composer.name);

        if (actual !== expected) {
            fail(`themes/elv/composer.json: name «${composer.name}» даёт код ${actual}, ожидается ${expected}`);
        }

        if (!composer.license) {
            fail('themes/elv/composer.json: нет license');
        }
    }

    const versionFile = path.join(themeDir, 'version.yaml');

    if (!fs.existsSync(versionFile)) {
        fail('themes/elv/version.yaml: нет файла версий (тема не обновится)');
    }
    else {
        const version = yamlValue(versionFile, '1.0.0');

        if (!version) {
            fail('themes/elv/version.yaml: нет записи 1.0.0');
        }
    }

    const screenshot = path.join(themeDir, 'screenshot.png');

    if (!fs.existsSync(screenshot)) {
        fail('themes/elv/screenshot.png: нет карточки продукта');
    }
    else {
        const bytes = fs.readFileSync(screenshot);
        const isPng = bytes.length > 8 && bytes[0] === 0x89 && bytes.toString('ascii', 1, 4) === 'PNG';

        if (!isPng) {
            fail('themes/elv/screenshot.png: файл не PNG');
        }

        if (bytes.length < 10 * 1024) {
            fail(`themes/elv/screenshot.png: подозрительно маленький (${bytes.length} байт)`);
        }
    }
}

function checkPlugin() {
    const composer = readJson(path.join(pluginDir, 'composer.json'));

    if (!composer) {
        return;
    }

    if (!/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/.test(String(composer.name || ''))) {
        fail(`plugins/elv/library/composer.json: name «${composer.name || '—'}» не в форме vendor/name`);
    }

    if (!String(composer.name || '').endsWith('-plugin')) {
        fail('plugins/elv/library/composer.json: имя пакета плагина должно кончаться на -plugin');
    }

    if (composer.type !== 'october-plugin') {
        fail(`plugins/elv/library/composer.json: type «${composer.type || '—'}» вместо october-plugin`);
    }
}

function checkLicenses() {
    const themeLicense = path.join(themeDir, 'LICENSE.md');
    const pluginLicense = path.join(pluginDir, 'LICENSE.md');

    for (const file of [themeLicense, pluginLicense]) {
        if (!fs.existsSync(file)) {
            fail(`${path.relative(root, file)}: нет текста лицензии покупателя`);
        }
        else if (fs.readFileSync(file, 'utf8').trim().length < 200) {
            fail(`${path.relative(root, file)}: текст лицензии подозрительно короткий`);
        }
    }

    if (fs.existsSync(themeLicense) && fs.existsSync(pluginLicense)) {
        const theme = fs.readFileSync(themeLicense, 'utf8').trim();
        const plugin = fs.readFileSync(pluginLicense, 'utf8').trim();

        if (theme !== plugin) {
            fail('LICENSE.md темы и плагина расходятся: у покупателя две разные лицензии');
        }
    }
}

function walk(dir, files = []) {
    if (!fs.existsSync(dir)) {
        return files;
    }

    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            if (entry.name === 'preview' || entry.name === 'node_modules') {
                continue;
            }

            walk(full, files);
        }
        else {
            files.push(full);
        }
    }

    return files;
}

function checkAbsolutePaths() {
    const extensions = new Set(['.php', '.yaml', '.yml', '.htm', '.html', '.js', '.mjs', '.json', '.md', '.css', '.txt']);
    const targets = [
        ...walk(path.join(pluginDir)),
        ...walk(themeDir),
        ...walk(path.join(root, 'config')),
    ];
    const pattern = /(^|[^A-Za-z0-9])([A-Za-z]):[\\/]/;

    for (const file of targets) {
        if (!extensions.has(path.extname(file).toLowerCase())) {
            continue;
        }

        const relative = path.relative(root, file);

        if (relative.includes('themes/elv/assets/vendor/')) {
            continue;
        }

        const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);

        lines.forEach((line, index) => {
            if (pattern.test(line)) {
                fail(`${relative}:${index + 1}: абсолютный путь в поставляемом коде`);
            }
        });
    }
}

function main() {
    checkTheme();
    checkPlugin();
    checkLicenses();
    checkAbsolutePaths();

    if (failures.length > 0) {
        console.error(`FAIL: ${failures.length} проблем упаковки`);

        for (const line of failures) {
            console.error('  - ' + line);
        }

        process.exit(1);
    }

    console.log(
        'OK: тема опознаётся как продукт (elv.elv, 1.0.0), у темы и плагина есть лицензия, '
        + 'карточка screenshot.png на месте, абсолютных путей в поставляемом коде нет'
    );
}

main();
