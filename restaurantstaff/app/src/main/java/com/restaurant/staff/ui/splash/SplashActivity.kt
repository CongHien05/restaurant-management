package com.restaurant.staff.ui.splash

import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import androidx.appcompat.app.AppCompatActivity
import com.restaurant.staff.RestaurantStaffApplication
import com.restaurant.staff.databinding.ActivitySplashBinding
import com.restaurant.staff.ui.auth.LoginActivity
import com.restaurant.staff.ui.main.MainActivity

class SplashActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySplashBinding
    private val splashDelay = 1000L // 2 seconds

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySplashBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Check if user is logged in after delay
        Handler(Looper.getMainLooper()).postDelayed({
            checkLoginStatus()
        }, splashDelay)
    }

    private fun checkLoginStatus() {
        val app = application as RestaurantStaffApplication

        if (app.authRepository.isLoggedIn()) {
            // User is logged in, go to main
            startActivity(Intent(this, MainActivity::class.java))
        } else {
            // User not logged in, go to login
            startActivity(Intent(this, LoginActivity::class.java))
        }

        finish()
    }
}
