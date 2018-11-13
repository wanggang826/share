var holdUser = function(page){
  var user = page.data.user;
  user.time = Date.now();

  page.setData({
    user: user
  });

  wx.setStorage({
    key: 'user',
    data: user,
  });
}

module.exports = holdUser;