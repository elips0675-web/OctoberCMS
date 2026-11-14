#!/usr/bin/env node
/**
 * Гейт «дерево готово к закрытию этапа».
 *
 *   node tools/check-clean-tree.mjs
 *
 * Падает (exit 1), если:
 *   - `git status --porcelain` что-то показывает (незакоммиченные файлы);
 *   - черновик владельца попал в индекс (`git ls-files`).
 *
 * Черновики владельца (AGENTS.md, раздел 7) лежат в `.gitignore` и в списке
 * `OWNER_DRAFTS` ниже: правка одного без другого бессмысленна, гейт ловит
 * именно попадание в индекс, а не отсутствие файла на диске.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const OWNER_DRAFTS = ['Архитектура', 'Продажа шаблона', 'Оценка', 'Что взять из WPShop', 'конструктор страниц', 'Как монетизировать', 'Laravel + Filament', 'Стучалка', 'Как сделать стучалку', 'Монетизация сборки', 'Бренд Elv шаблон', 'October CMS для лендинга'];

function git(...args) {
  return execFileSync('git', args, { cwd: root, encoding: 'utf8' });
}

let failed = false;

let tracked = '';
try {
  tracked = git('ls-files', '-z');
} catch (error) {
  console.error('FAIL: git недоступен — ' + error.message);
  process.exit(1);
}

const leaked = tracked
  .split('\0')
  .filter(Boolean)
  .filter((file) => OWNER_DRAFTS.some((draft) => file.includes(draft)));

if (leaked.length) {
  failed = true;
  console.error('FAIL: черновик владельца в индексе — ' + leaked.join(', '));
  console.error('      уберите из индекса: git rm --cached "…"');
}

const status = git('status', '--porcelain=v1', '-uall')
  .split('\n')
  .filter(Boolean);

if (status.length) {
  failed = true;
  console.error('FAIL: незакоммиченные изменения — ' + status.length + ' шт.');
  status.slice(0, 20).forEach((line) => console.error('      ' + line));
  if (status.length > 20) {
    console.error('      … и ещё ' + (status.length - 20));
  }
}

if (failed) {
  console.error('Дерево не готово: закоммитьте поимённо или добавьте файл в .gitignore.');
  process.exit(1);
}

console.log('OK: дерево чистое, черновиков владельца в индексе нет');
