FROM node:22-alpine

WORKDIR /app
RUN corepack enable && corepack prepare pnpm@9.15.4 --activate
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --prod --frozen-lockfile
COPY server.js ./

USER node
EXPOSE 8081
HEALTHCHECK --interval=20s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -q --spider http://127.0.0.1:8081/health || exit 1

CMD ["node", "server.js"]
