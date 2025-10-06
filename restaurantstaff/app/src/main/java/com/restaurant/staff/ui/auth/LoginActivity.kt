package com.restaurant.staff.ui.auth

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.restaurant.staff.RestaurantStaffApplication
import com.restaurant.staff.data.model.Resource
import com.restaurant.staff.data.remote.ApiClient
import com.restaurant.staff.databinding.ActivityLoginBinding
import com.restaurant.staff.ui.main.MainActivity
import kotlinx.coroutines.launch

class LoginActivity : AppCompatActivity() {

    private lateinit var binding: ActivityLoginBinding
    private val viewModel: LoginViewModel by viewModels {
        LoginViewModelFactory((application as RestaurantStaffApplication).authRepository)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupUI()
        observeViewModel()

        // Check if user is already logged in AND token is valid
        if (viewModel.isLoggedIn() && !ApiClient.isTokenExpired()) {
            android.util.Log.d("LoginActivity", "User already logged in with valid token")
            navigateToMain()
            return
        } else if (viewModel.isLoggedIn() && ApiClient.isTokenExpired()) {
            android.util.Log.d("LoginActivity", "User logged in but token expired, auto logging out")
            lifecycleScope.launch {
                viewModel.logout().collect { resource ->
                    when (resource) {
                        is Resource.Success -> {
                            android.util.Log.d("LoginActivity", "Auto logout successful")
                        }
                        is Resource.Error -> {
                            android.util.Log.d("LoginActivity", "Auto logout error: ${resource.message}")
                        }
                        is Resource.Loading -> {
                            android.util.Log.d("LoginActivity", "Auto logout in progress...")
                        }
                    }
                }
            }
        }

        // Pre-fill username if remembered
        viewModel.getLastUsername()?.let { username ->
            binding.etUsername.setText(username)
            binding.cbRememberMe.isChecked = viewModel.shouldRememberLogin()
        }
    }

    private fun setupUI() {
        binding.btnLogin.setOnClickListener {
            val username = binding.etUsername.text.toString().trim()
            val password = binding.etPassword.text.toString()
            val rememberMe = binding.cbRememberMe.isChecked

            if (validateInput(username, password)) {
                viewModel.login(username, password, rememberMe)
            }
        }

        binding.tvForgotPassword.setOnClickListener {
            // TODO: Implement forgot password functionality
            Toast.makeText(this, "Liên hệ quản trị viên để đặt lại mật khẩu", Toast.LENGTH_LONG).show()
        }
    }

    private fun observeViewModel() {
        lifecycleScope.launch {
            viewModel.loginState.collect { resource ->
                when (resource) {
                    null -> {
                        // Initial state, do nothing
                    }
                    is Resource.Loading -> {
                        showLoading(true)
                    }
                    is Resource.Success -> {
                        showLoading(false)
                        Toast.makeText(this@LoginActivity, "Đăng nhập thành công!", Toast.LENGTH_SHORT).show()
                        navigateToMain()
                    }
                    is Resource.Error -> {
                        showLoading(false)
                        Toast.makeText(this@LoginActivity, resource.message, Toast.LENGTH_LONG).show()
                    }
                }
            }
        }
    }

    private fun validateInput(username: String, password: String): Boolean {
        if (username.isEmpty()) {
            binding.etUsername.error = "Vui lòng nhập tên đăng nhập"
            binding.etUsername.requestFocus()
            return false
        }

        if (password.isEmpty()) {
            binding.etPassword.error = "Vui lòng nhập mật khẩu"
            binding.etPassword.requestFocus()
            return false
        }

        if (password.length < 6) {
            binding.etPassword.error = "Mật khẩu phải có ít nhất 6 ký tự"
            binding.etPassword.requestFocus()
            return false
        }

        return true
    }

    private fun showLoading(isLoading: Boolean) {
        binding.progressBar.visibility = if (isLoading) View.VISIBLE else View.GONE
        binding.btnLogin.isEnabled = !isLoading
        binding.etUsername.isEnabled = !isLoading
        binding.etPassword.isEnabled = !isLoading
    }

    private fun navigateToMain() {
        val intent = Intent(this, MainActivity::class.java)
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        startActivity(intent)
        finish()
    }
}
