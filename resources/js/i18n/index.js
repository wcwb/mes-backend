import { createI18n } from 'vue-i18n';
import zhCN from './zh-CN';

const messages = {
  'zh-CN': zhCN,
};

export default createI18n({
  legacy: false,
  locale: 'zh-CN', // 设置默认语言
  fallbackLocale: 'zh-CN', // 设置回退语言
  messages,
}); 