import dayjs from 'dayjs'

/**
 * 格式化日期 YYYY-MM-DD
 */
export const formatDate = (val) => {
  if (!val) return '-'
  return dayjs(val).format('YYYY-MM-DD')
}

/**
 * 格式化日期时间 YYYY-MM-DD HH:mm:ss
 */
export const formatDateTime = (val) => {
  if (!val) return '-'
  return dayjs(val).format('YYYY-MM-DD HH:mm:ss')
}

/**
 * 格式化货币 ¥1,234.56
 */
export const formatCurrency = (val) => {
  if (val === null || val === undefined || val === '') return '-'
  return '¥' + parseFloat(val).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
}
