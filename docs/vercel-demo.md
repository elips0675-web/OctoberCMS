# Публичное демо на Vercel — что нажать

Демо шаблона — статический снимок `demo-static/` (110 страниц, формы отключены),
его отдаёт Vercel по `vercel.json:2` (`outputDirectory: demo-static`).
Проект уже создан: `https://vercel.com/vadims-projects-74cb21aa/octobercms`,
репозиторий подключён — `https://github.com/elips0675-web/OctoberCMS`.

## Почему просит войти через Google

Проверено 19.11.2026 снаружи, без авторизации:

```text
https://octobercms-git-main-vadims-projects-74cb21aa.vercel.app
  → 302 Location: https://vercel.com/sso-api?url=…&nonce=…
  → Set-Cookie: _vercel_sso_nonce=…

https://octobercms.vercel.app
  → 404 X-Vercel-Error: DEPLOYMENT_NOT_FOUND

https://octobercms-vadims-projects-74cb21aa.vercel.app
  → 404 X-Vercel-Error: DEPLOYMENT_NOT_FOUND
```

Два независимых факта:

1. **Deployment Protection = Vercel Authentication** (это и есть «вход гугла»).
   Редирект на `sso-api` — его подпись; страница защищена целиком, поэтому её
   видит только владелец аккаунта Vercel.
2. **Продакшен-деплоя нет**: домен проекта `*.vercel.app` отвечает
   `DEPLOYMENT_NOT_FOUND`, значит деплой не повышен до Production — живой
   только алиас ветки `git-main`.

Правки в репозитории этого не лечат: защита и повышение деплоя живут
в настройках проекта Vercel, а не в `vercel.json` (такой опции в схеме нет).

## Два действия владельца (≈2 минуты)

1. **Снять защиту.** Проект `octobercms` → **Settings** → **Deployment
   Protection** → **Vercel Authentication**: выбрать **Disabled** → **Save**.
   Заодно проверить, что не включены `Password Protection` и `Trusted IPs`.
2. **Повысить деплой до продакшена.** **Deployments** → у свежей записи со
   статусом **Ready** меню **⋯** → **Promote to Production**.
   После этого появляется домен `https://octobercms-vadims-projects-74cb21aa.vercel.app`.

Если в проекте нет ни одного успешного деплоя — **Deployments** → **Redeploy**
у последнего коммита `main` (сборка идёт по `vercel.json`, Framework Preset
**Other**, Build Command пустой).

## Проверка

```powershell
curl.exe -sS -o NUL -w "%{http_code}`n" https://octobercms-vadims-projects-74cb21aa.vercel.app/
curl.exe -sS -o NUL -w "%{http_code}`n" https://octobercms-vadims-projects-74cb21aa.vercel.app/services/
curl.exe -sS -o NUL -w "%{http_code}`n" https://octobercms-vadims-projects-74cb21aa.vercel.app/services/sauna/
```

Ожидается `200` на всех трёх и отсутствие заголовка `x-vercel-error`.
Редирект `302` на `vercel.com/sso-api` = защита ещё включена.

## После публикации

1. Пересобрать снимок под домен демо (canonical, sitemap, RSS):

   ```powershell
   php tools/export-static.php --origin=https://octobercms-vadims-projects-74cb21aa.vercel.app
   node tools/check-static-demo.mjs --origin=https://octobercms-vadims-projects-74cb21aa.vercel.app
   ```

2. Вписать адрес в `README.md` (раздел «Демо») и в поле **Website** репозитория
   на GitHub вместо витрины `https://elvstudio.vercel.app`.

## Если нужен свой домен

**Settings** → **Domains** → **Add** (например `demo.elv.studio`), в DNS
прописать `CNAME demo → cname.vercel-dns.com`; сертификат Let's Encrypt Vercel
выпускает сам. После привязки — та же пересборка с `--origin=https://demo.elv.studio`.

## Вместо кликов: один скрипт

`tools/vercel-demo.mjs` делает то же по API: снимает Vercel Authentication и
пароль, повышает последний готовый деплой до Production, привязывает
`octobercms.vercel.app` и проверяет адрес снаружи.

```powershell
$env:VERCEL_TOKEN = "<токен>"      # https://vercel.com/account/tokens
node tools/vercel-demo.mjs
```

Без токена скрипт выходит с кодом 2 и ничего не меняет; токен не печатается и
не сохраняется. Код возврата `0` — демо открылось (`200`, без
`x-vercel-error`).

## Ручные запросы (если нужен контроль по шагам)

Если у владельца есть токен (`vercel.com/account/tokens`), защиту и продакшен
можно переключить без интерфейса:

```powershell
$token = "<токен>"
$project = "octobercms"

# 1. снять Vercel Authentication (ssoProtection = null)
curl.exe -sS -X PATCH "https://api.vercel.com/v9/projects/$project" `
  -H "Authorization: Bearer $token" -H "Content-Type: application/json" `
  -d "{\"ssoProtection\":null,\"passwordProtection\":null}"

# 2. узнать последний деплой и повысить его
curl.exe -sS "https://api.vercel.com/v6/deployments?projectId=$project&limit=1" `
  -H "Authorization: Bearer $token"
curl.exe -sS -X POST "https://api.vercel.com/v10/projects/$project/promote/<deploymentId>" `
  -H "Authorization: Bearer $token"
```

Токен в репозиторий и в переписку не попадает: он нужен только для этих двух
запросов и удаляется из переменной сессии после.
