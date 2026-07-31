FROM node:20-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

EXPOSE 5173

# El -- --host es indispensable para que Vite responda dentro de Docker
CMD ["npm", "run", "dev", "--", "--host"]