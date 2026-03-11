import request from './index'

export const generateQRCode = (id) => {
  return request({
    url: `/assets/${id}/qrcode`,
    method: 'post'
  })
}

export const batchGenerateQRCode = (data) => {
  return request({
    url: '/assets/qrcode/batch',
    method: 'post',
    data
  })
}

export const downloadQRCode = (id) => {
  return request({
    url: `/assets/${id}/qrcode/download`,
    method: 'get',
    responseType: 'blob'
  })
}

export const printLabel = (id) => {
  return request({
    url: `/assets/${id}/qrcode/print`,
    method: 'get'
  })
}
