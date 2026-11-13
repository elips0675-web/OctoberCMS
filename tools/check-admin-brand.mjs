#!/usr/bin/env node
/**
 * Брендинг админки: конфиг, файлы и живая страница входа (P2-29).
 *
 * Что проверяется:
 *   - `config/backend.php` → `brand.enabled` включён, а `menu_mode`,
 *     `color_mode`, `color_palette` входят в белые списки ядра
 *     (`modules/backend/models/BrandSetting.php`, `HasPalettes`);
 *   - каждый путь из `logo_path`, `favicon_path`, `menu_logo_path`,
 *     `dashboard_icon_path`, `stylesheet_path` (`~/…` = корень проекта)
 *     существует и не пустой — иначе на странице входа будет 404-картинка;
 *   - `brand_styles.css` не тянет внешние ресурсы (`@import`, `http(s)://`)
 *     и содержит акцентный цвет сайта;
 *   - живая страница входа (`BACKEND_URI`, по умолчанию `/admin`) отдаёт 200,
 *     в `<title>` имя приложения, а в инлайновом CSS — скомпилированную
 *     палитру (`--bs-primary` + цвет из `brand_styles.css`).
 *
 * Последняя проверка ловит ловушку ядра: `custom_css` засевается из
 * `stylesheet_path` только при первом создании настроек
 * (`modules/backend/models/BrandSetting.php:102-106`). Если запись уже была
 * создана, правка файла не видна, пока настройки не сброшены.
 *
 *   node tools/check-admin-brand.mjs [--base=http://127.0.0.1:8080]
 *
 * Требуется запущенный `php artisan serve` (порт по умолчанию 8080).
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');

const MENU_MODES = ['inline', 'text', 'tile', 'collapse', 'icons', 'left'];
const COLOR_MODES = ['light', 'dark', 'auto'];
const PALETTES = ['default', 'classic', 'oxford', 'console', 'valentino', 'punch'];
const ASSET_KEYS = ['logo_path', 'favicon_path', 'menu_logo_path', 'dashboard_icon_path', 'stylesheet_path'];

function brandBlock() {
    const file = path.join(root, 'config/backend.php');
    const source = fs.readFileSync(file, 'utf8');
    const start = source.indexOf("'brand' => [");

    if (start < 0) {
        throw new Error('config/backend.php: нет раздела brand');
    }

    const end = source.indexOf('\n    ],', start);

    if (end < 0) {
        throw new Error('config/backend.php: раздел brand не закрыт');
    }

    return source.slice(start, end);
}

function scalar(block, key) {
    const match = new RegExp(`'${key}'\\s*=>\\s*'([^']*)'`).exec(block);

    return match ? match[1] : '';
}

function flag(block, key) {
    const match = new RegExp(`'${key}'\\s*=>\\s*(true|false)`).exec(block);

    return match ? match[1] === 'true' : null;
}

function appName(block) {
    const envFile = path.join(root, '.env');
    let name = '';

    if (fs.existsSync(envFile)) {
        const line = fs.readFileSync(envFile, 'utf8')
            .split(/\r?\n/)
            .find((row) => /^\uFEFF?APP_NAME=/.test(row));

        if (line) {
            name = line.slice(line.indexOf('=') + 1).trim().replace(/^["']|["']$/g, '');
        }
    }

    if (name) {
        return name;
    }

    const fallback = /'app_name'\s*=>\s*env\(\s*'APP_NAME'\s*,\s*'([^']*)'/.exec(block);

    return fallback ? fallback[1] : '';
}

function backendUri() {
    const envFile = path.join(root, '.env');

    if (!fs.existsSync(envFile)) {
        return 'admin';
    }

    const line = fs.readFileSync(envFile, 'utf8')
        .split(/\r?\n/)
        .find((row) => /^BACKEND_URI=/.test(row));

    if (!line) {
        return 'admin';
    }

    return line.slice('BACKEND_URI='.length).trim().replace(/^["']|["']$/g, '').replace(/^\/|\/$/g, '') || 'admin';
}

function symbolize(location) {
    return location.startsWith('~/') ? path.join(root, location.slice(2)) : location;
}

async function main() {
    const failures = [];
    const block = brandBlock();

    if (flag(block, 'enabled') !== true) {
        failures.push('brand.enabled выключен — админка работает на дефолтном оформлении');
    }

    const menuMode = scalar(block, 'menu_mode');
    if (!MENU_MODES.includes(menuMode)) {
        failures.push(`brand.menu_mode «${menuMode}», допустимо: ${MENU_MODES.join(', ')}`);
    }

    const colorMode = scalar(block, 'color_mode');
    if (!COLOR_MODES.includes(colorMode)) {
        failures.push(`brand.color_mode «${colorMode}», допустимо: ${COLOR_MODES.join(', ')}`);
    }

    const palette = scalar(block, 'color_palette');
    if (!PALETTES.includes(palette)) {
        failures.push(`brand.color_palette «${palette}», допустимо: ${PALETTES.join(', ')}`);
    }

    const assets = {};

    for (const key of ASSET_KEYS) {
        const location = scalar(block, key);

        if (!location) {
            failures.push(`brand.${key} пустой`);
            continue;
        }

        const file = symbolize(location);
        assets[key] = file;

        if (!fs.existsSync(file)) {
            failures.push(`brand.${key}: файла нет (${file})`);
            continue;
        }

        if (fs.statSync(file).size === 0) {
            failures.push(`brand.${key}: пустой файл (${file})`);
        }
    }

    if (assets.stylesheet_path && fs.existsSync(assets.stylesheet_path)) {
        const css = fs.readFileSync(assets.stylesheet_path, 'utf8');

        if (/@import\b/.test(css)) {
            failures.push('brand_styles.css: @import — внешняя зависимость в оформлении админки');
        }

        if (/url\(\s*['"]?https?:/i.test(css)) {
            failures.push('brand_styles.css: внешний url() — админка должна грузить только свои файлы');
        }

        if (!css.includes('--bs-primary')) {
            failures.push('brand_styles.css: нет --bs-primary — палитра не переопределена');
        }
    }

    const expectedName = appName(block);
    const adminUri = `/${backendUri()}`;
    const response = await fetch(base + adminUri);
    const html = await response.text();

    if (response.status !== 200) {
        failures.push(`${adminUri}: HTTP ${response.status}, ожидался 200`);
    }

    if (expectedName && !html.includes(expectedName)) {
        failures.push(`${adminUri}: в разметке нет имени приложения «${expectedName}»`);
    }

    if (!html.includes('--bs-primary')) {
        failures.push(`${adminUri}: нет скомпилированной палитры (--bs-primary) — brand CSS не попал на страницу`);
    }

    if (!html.includes('2563eb')) {
        failures.push(`${adminUri}: нет акцентного цвета из brand_styles.css — настройки бренда не пересозданы`);
    }

    for (const key of ['favicon_path', 'logo_path']) {
        if (!assets[key]) {
            continue;
        }

        const name = path.basename(assets[key]);

        if (!html.includes(name)) {
            failures.push(`${adminUri}: в разметке нет ${name} (brand.${key})`);
        }
    }

    if (failures.length > 0) {
        console.error(`FAIL: ${failures.length} проблем`);
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(
        `OK: брендинг админки включён (${menuMode}, ${colorMode}, ${palette}), `
        + `${ASSET_KEYS.length} файлов на месте, ${adminUri} отдаёт имя и палитру`
    );
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
