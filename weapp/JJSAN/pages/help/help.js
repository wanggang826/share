var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');

Page({

  /**
   * 页面的初始数据
   */
  data: {
    user: '',
    items: [
      { status: false, question: '如何租借JJ伞？', answer: '答：用微信或支付宝扫描伞柜二维码，关注街借伞公众号后，充值押金成功即可取走JJ伞' },
      { status: false, question: '如何归还JJ伞？', answer: '答：', content: ['1.将伞柄插入伞柜的伞槽中，稍等3-5秒，绿灯亮时，语音提示还伞成功即可', '2.可通过JJ伞公众号的“附近网点”找到最近的网点，任意网点都可以归还'] },
      { status: false, question: 'JJ伞如何收费？', answer: '答：借伞时，需缴纳押金。还伞后，用伞费用会从押金中扣减，剩余押金将自动转为可提现的余额。用伞计费标准详见借伞过程中的提示，收费计时按12小时为节点' },
      { status: false, question: '如何退回押金？', answer: '答：无需手动退回押金。还伞后，用伞费用会从押金中扣减，剩余押金将自动转为余额，余额可在“个人中心-我的钱包”中提现' },
      { status: false, question: '提现后如何确认到账？', answer: '答：', content: ['1.微信零钱支付：到账查询点击微信→钱包→零钱→零钱明细，查看您的提现记录，以确认提现是否到账', '2.微信绑定银行卡支付：到账时微信支付会有退款到账通知，若需确认，请您查看银行交易明细，是否有提现金额入账', '3.微信绑定信用卡支付：信用卡客户和银行卡客户相同，但需确认自己的结账日期，根据结账日期确认查询已出账单还是未出账单'] },
      {
        status: false, question: '扫码取伞时，伞没有弹出？', answer: '答：伞槽故障，请尝试重新租借，如需帮助，请致电客服400-900-8113'
      },
      {
        status: false, question: '取伞后发现伞被损坏？', answer: '答：请在两分钟内将伞插回机器的任意伞槽，不产生任何费用'
      },
      {
        status: false, question: '归还时提示归还失败？', answer: '答：请将伞柄完全卡入伞槽中。另外，当伞柜断网或断电情况下也会导致还伞失败，请及时联系客服400-900-8113'
      },
      {
        status: false, question: '伞柄卡入伞槽，没收到还伞成功提示？', answer: '答：', content: ['1.请检查伞柄是否完全卡入伞槽中', '2.在一些网络不佳的环境下，提示会有延迟，请尝试刷新', '3.如需帮助，请致电客服400-900 - 8113或在公众号留言']
      },
      {
        status: false, question: '卡槽已满，无法还伞', answer: '答：', content: ['1.可通过JJ伞公众号的“附近网点”找到最近的网点，任意网点都可以归还', '2.致电客服400-900 - 8113或在公众号留言']
      },
      {
        status: false, question: 'JJ伞遗失？', answer: '答：遗失后请及时登记处理，将扣除押金做为赔付。登记方式如下:', content: ['1.自助登记：JJ伞公众号的“个人中心”，进入“借还记录”，展开要登记遗失的订单，点击“登记遗失”', '2.留言登记：在JJ伞公众号留言', '3.电话登记：致电客服热点400-900 - 8113']
      }]
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    var page = this;
    getUser(page);

  },
  showDetails: function (event) {
    console.log(event);
    var page = this;
    var items = page.data.items;

    var id = event.currentTarget.id;

    if (items[id].status) {
      items[id].status = !items[id].status;
    } else {
      for (var i = 0; i < items.length; i++) {
        items[i].status = false;
      }
      items[id].status = true;
    }

    page.setData({
      items: items
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