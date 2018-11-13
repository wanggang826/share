var comsumerAgreement = require('../../utils/agreement.js');
var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');

Page({

  /**
   * 页面的初始数据
   */
  data: {
    consumer: ''
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {

    var page = this;
    getUser(page);

    page.setData({
      consumer: comsumerAgreement
    });
  },

  choose: function (e) {
    console.log("event");
    console.log(e.currentTarget.id);

    var page = this;
    var consumer = page.data.consumer;

    var id = e.currentTarget.id;
    var len = consumer.items.length;
    for (var i = 0; i < len; i++) {
      if (i == id) {
        consumer.items[i].show = !consumer.items[i].show;
        continue;
      }
      consumer.items[i].show = false;
    }

    page.setData({
      consumer: consumer
    })
  },

  call: function (event) {
    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };

    wx.request({
      url: URL,
      data: {
        mod: 'api',
        act: 'weapp',
        opt: 'form_id',
        form_id: formId,
        session: page.data.user.session
      },
      success: function (res) {
      }
    })

    wx.makePhoneCall({
      phoneNumber: '4009008113',
      fail: function () {
        wx.showModal({
          title: '电话提示',
          content: '请拨打客服电话4009008113',
          showCancel: 'false'
        })
      }
    })
  }
})