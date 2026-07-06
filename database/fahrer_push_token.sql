-- Expo push-токен водителя (мобильное приложение сохраняет после логина).
ALTER TABLE fahrer
  ADD COLUMN push_token VARCHAR(255) NULL;
