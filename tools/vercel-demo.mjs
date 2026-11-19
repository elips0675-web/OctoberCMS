#!/usr/bin/env node
/**
 * vercel-demo — открыть демо шаблона без кликов в интерфейсе Vercel.
 *
 * Что делает по API:
 *   1) снимает Deployment Protection (Vercel Authentication и пароль);
 *   2) повышает последний готовый деплой до Production;
 *   3) привязывает домен `octobercms.vercel.app`, если он ещё не привязан;
 *   4) проверяет снаружи: ждёт `200` и отсутствие `x-vercel-error`.
 *
 *   $env:VERCEL_TOKEN = "<токен>"      # https://vercel.com/account/tokens
 *   node tools/vercel-demo.mjs
 *
 * Необязательные переменные: `VERCEL_TEAM` (slug команды),
 * `VERCEL_PROJECT`, `VERCEL_DOMAIN`. Токен не печатается и не сохраняется.
 */

const token = process.env.VERCEL_TOKEN;
if (!token) {
  console.error('нет VERCEL_TOKEN: создайте токен на https://vercel.com/account/tokens');
  process.exit(2);
}

const team = process.env.VERCEL_TEAM || 'vadims-projects-74cb21aa';
const projectName = process.env.VERCEL_PROJECT || 'octobercms';
const domain = process.env.VERCEL_DOMAIN || 'octobercms.vercel.app';
const api = 'https://api.vercel.com';
const query = `?slug=${encodeURIComponent(team)}`;

async function call(method, path, body) {
  const res = await fetch(`${api}${path}${query}`, {
    method,
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await res.text();
  let json = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    json = null;
  }
  return { status: res.status, ok: res.ok, json, text };
}

function reason(r) {
  return r.json?.error?.message || r.json?.message || r.json?.error?.code || r.text.slice(0, 200) || 'без описания';
}

function fail(step, r) {
  console.error(`✖ ${step}: HTTP ${r.status} — ${reason(r)}`);
  process.exitCode = 1;
}

const project = await call('GET', `/v9/projects/${encodeURIComponent(projectName)}`);
if (!project.ok) {
  fail(`проект «${projectName}» в команде «${team}»`, project);
  process.exit(1);
}
const projectId = project.json.id;
const branch = project.json.link?.productionBranch || '—';
console.log(`проект: ${project.json.name} (${projectId}), production branch: ${branch}`);

const protection = await call('PATCH', `/v9/projects/${projectId}`, {
  ssoProtection: null,
  passwordProtection: null,
});
if (protection.ok) {
  console.log('✔ защита снята: ssoProtection = null, passwordProtection = null');
} else {
  fail('снять Deployment Protection', protection);
}

const deployments = await call('GET', `/v6/deployments?projectId=${projectId}&limit=10`);
const list = deployments.json?.deployments || [];
const ready = list.find((d) => (d.readyState || d.state) === 'READY');
const production = ready && (ready.target === 'production' || ready.readyState === 'READY');
if (!deployments.ok) {
  fail('список деплоев', deployments);
} else if (!ready) {
  console.error('✖ нет готового деплоя: нажмите Redeploy у коммита в main, затем повторите запуск');
  process.exitCode = 1;
} else {
  console.log(`последний готовый деплой: ${ready.url} (${ready.readyState || ready.state}, target: ${ready.target || '—'})`);
  const promote = await call('POST', `/v10/projects/${projectId}/promote/${ready.uid}`);
  if (promote.ok) {
    console.log('✔ деплой повышен до Production');
  } else if (promote.status === 409) {
    console.log('… продакшен уже на этом деплое');
  } else {
    fail('повысить деплой до Production', promote);
  }
  if (!production) {
    console.log(`  (деплой пришёл из ветки «${ready.meta?.githubCommitRef || '—'}»; при работе через Git закрепите main: Settings → Git → Production Branch)`);
  }
}

const domains = await call('GET', `/v9/projects/${projectId}/domains`);
const attached = (domains.json?.domains || []).some((d) => d.name === domain);
if (!domains.ok) {
  fail('список доменов', domains);
} else if (attached) {
  console.log(`… домен уже привязан: ${domain}`);
} else {
  const add = await call('POST', `/v10/projects/${projectId}/domains`, { name: domain });
  if (add.ok) {
    console.log(`✔ домен привязан: ${domain}`);
  } else if (add.status === 409) {
    console.log(`… домен уже привязан: ${domain}`);
  } else {
    fail(`привязать домен ${domain}`, add);
    console.error('  свободные поддомены *.vercel.app выдаются только владельцу имени: если занято, добавьте свой домен в Settings → Domains');
  }
}

await new Promise((resolve) => setTimeout(resolve, 5000));
const probe = await fetch(`https://${domain}/`, { redirect: 'manual' });
const location = probe.headers.get('location') || '';
const error = probe.headers.get('x-vercel-error') || '';
console.log(`проверка https://${domain}/ → ${probe.status}${error ? ` ${error}` : ''}${location ? ` → ${location}` : ''}`);
if (probe.status !== 200 || error) {
  console.error('✖ демо ещё не открывается: повторите через минуту или проверьте Deployment Protection');
  process.exitCode = 1;
}

process.exit(process.exitCode || 0);
