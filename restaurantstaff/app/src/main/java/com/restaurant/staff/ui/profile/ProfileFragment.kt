package com.restaurant.staff.ui.profile

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import com.restaurant.staff.RestaurantStaffApplication
import com.restaurant.staff.databinding.FragmentProfileBinding
import com.restaurant.staff.ui.auth.LoginViewModel
import com.restaurant.staff.ui.auth.LoginViewModelFactory

class ProfileFragment : Fragment() {

    private var _binding: FragmentProfileBinding? = null
    private val binding get() = _binding!!

    private val authViewModel: LoginViewModel by viewModels {
        LoginViewModelFactory((requireActivity().application as RestaurantStaffApplication).authRepository)
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentProfileBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        setupUI()
    }

    private fun setupUI() {
        val app = requireActivity().application as RestaurantStaffApplication
        val user = app.authRepository.getCurrentUser()

        user?.let {
            binding.textFullName.text = it.fullName
            binding.textUsername.text = "@${it.username}"
            binding.textRole.text = when (it.role) {
                "waiter" -> "Nhân viên phục vụ"
                "manager" -> "Quản lý"
                "admin" -> "Quản trị viên"
                "kitchen" -> "Nhân viên bếp"
                else -> it.role
            }
            binding.textStaffCode.text = "Mã NV: ${it.id}"

            if (!it.phone.isNullOrEmpty()) {
                binding.textPhone.text = "SĐT: ${it.phone}"
                binding.textPhone.visibility = View.VISIBLE
            }

            if (!it.email.isNullOrEmpty()) {
                binding.textEmail.text = "Email: ${it.email}"
                binding.textEmail.visibility = View.VISIBLE
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
