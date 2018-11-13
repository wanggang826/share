var getLocation = function (page) {
  var count = 0;
  var locate = setTimeout(function fn() {
    wx.getLocation({
      success: function (res) {
        clearInterval(locate);
        var latitude = res.latitude;
        var longitude = res.longitude;
        page.setData({
          latitude: latitude,
          longitude: longitude
        });
      },
      fail: function () {
        if (count == 0) {
          wx.showToast({
            title: '请确认定位是否打开',
            duration: 2000
          });
        }
        locate = setTimeout(fn,50);
      },
      complete: function () {
        count++;
      }
    })
  }, 50);
}

module.exports = getLocation;
