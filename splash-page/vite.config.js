import { defineConfig } from "vite";

export default defineConfig({
  server: {
    proxy: {
      "/api": {
        target: "http://localhost", // Replace with your PHP server URL
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, "/splash-page/api"),
      },
    },
  },
});
