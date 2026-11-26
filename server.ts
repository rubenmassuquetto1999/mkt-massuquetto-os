import express from 'express';
import path from 'path';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';
import { PHP, PHPRequestHandler, type HTTPMethod } from '@php-wasm/universal';

const PORT = 3000;
const PHP_ROOT = '/var/www/html';
const HOST_PHP_DIR = path.resolve(process.cwd(), 'php');

async function startServer() {
  const runtimeId = await loadNodeRuntime('8.3', {
    emscriptenOptions: {
      processId: 1,
    },
  });
  const php = new PHP(runtimeId);

  php.mkdirTree(PHP_ROOT);
  php.mount(PHP_ROOT, createNodeFsMountHandler(HOST_PHP_DIR));
  php.chdir(PHP_ROOT);

  // Set timezone to America/Sao_Paulo for accurate timestamps in db.json
  php.mkdirTree('/usr/local/etc/php/conf.d');
  php.writeFile(
    '/usr/local/etc/php/conf.d/app.ini',
    'date.timezone = "America/Sao_Paulo"\n'
  );

  const appUrl = process.env.APP_URL || `http://localhost:${PORT}`;
  const requestHandler = new PHPRequestHandler({
    php,
    documentRoot: PHP_ROOT,
    absoluteUrl: appUrl,
    cookieStore: false,
  });

  const app = express();
  app.set('trust proxy', true);

  // Capture raw request body so PHP parses form-urlencoded and multipart payloads natively
  app.use(express.raw({ type: '*/*', limit: '10mb' }));

  app.all('*', async (req, res) => {
    try {
      const cleanPath = req.path.toLowerCase();
      if (cleanPath === '/db.json' || cleanPath.endsWith('/db.json')) {
        res.status(403).send('Forbidden');
        return;
      }

      const headers: Record<string, string> = {};
      for (const [key, value] of Object.entries(req.headers)) {
        if (typeof value === 'string') {
          headers[key.toLowerCase()] = value;
        } else if (Array.isArray(value)) {
          headers[key.toLowerCase()] = value.join(', ');
        }
      }

      const method = req.method.toUpperCase() as HTTPMethod;
      const hasBody =
        method !== 'GET' &&
        method !== 'HEAD' &&
        Buffer.isBuffer(req.body) &&
        req.body.length > 0;

      const phpResponse = await requestHandler.request({
        url: req.originalUrl || '/',
        method,
        headers,
        body: hasBody ? new Uint8Array(req.body) : undefined,
      });

      const isLocalPlainHttp =
        (req.headers.host || '').startsWith('localhost:') &&
        !req.headers['x-forwarded-proto'] &&
        !String(req.headers.origin || '').startsWith('https://') &&
        !String(req.headers.referer || '').startsWith('https://') &&
        String(req.headers['user-agent'] || '').startsWith('curl/');

      const isHttps =
        !isLocalPlainHttp &&
        (req.secure ||
          String(req.headers['x-forwarded-proto'] || '').includes('https') ||
          String(req.headers.origin || '').startsWith('https://') ||
          String(req.headers.referer || '').startsWith('https://') ||
          appUrl.startsWith('https://'));

      for (const [name, values] of Object.entries(phpResponse.headers)) {
        const lowerName = name.toLowerCase();
        if (lowerName === 'transfer-encoding' || lowerName === 'content-length') {
          continue;
        }
        if (lowerName === 'set-cookie') {
          const adjusted = values.map((cookie) => {
            if (isHttps && !/samesite=/i.test(cookie)) {
              return `${cookie}; SameSite=None; Secure; Partitioned`;
            }
            return cookie;
          });
          res.setHeader('Set-Cookie', adjusted);
        } else if (values.length === 1) {
          res.setHeader(name, values[0]);
        } else if (values.length > 1) {
          res.setHeader(name, values);
        }
      }

      res.status(phpResponse.httpStatusCode || 200);
      res.send(Buffer.from(phpResponse.bytes));
    } catch (err) {
      console.error('PHP request error:', err);
      res.status(500).send('Internal Server Error');
    }
  });

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`Massuquetto OS (PHP 8.3) rodando em http://0.0.0.0:${PORT}`);
  });
}

startServer().catch((err) => {
  console.error('Failed to start PHP server:', err);
  process.exit(1);
});
